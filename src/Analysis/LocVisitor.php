<?php

/** @noinspection ALL */

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait {
        __construct as private __visitorTraitConstruct;
    }

    public function __construct(
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
        private readonly MetricsReaderInterface $reader,
    ) {
        $this->__visitorTraitConstruct($writer, $registry);
    }

    /**
     * @var array<int, string>
     */
    private array $currentFunctionName = [];

    /**
     * @var array<int, string>
     */
    private array $currentClassName = [];

    /**
     * @var array<int, string>
     */
    private array $currentMethodName = [];

    /**
     * @var array<string, array<int, Node\Stmt>>
     */
    private array $functionNodes = [];

    private PrettyPrinter\Standard $prettyPrinter;

    private int $insideLloc = 0;

    private int $insideFunctionLloc = 0;

    private int $insideMethodLloc = 0;

    private int $fileHtmlLoc = 0;

    /**
     * @var array<string, int>
     */
    private array $classHtmlLoc = [];

    /**
     * @var array<string, int>
     */
    private array $functionHtmlLoc = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $methodHtmlLoc = [];

    public function beforeTraverse(array $nodes): ?array
    {
        $this->prettyPrinter = new PrettyPrinter\Standard();
        $this->insideLloc = 0;
        $this->insideFunctionLloc = 0;
        $this->insideMethodLloc = 0;
        $this->fileHtmlLoc = 0;
        $this->functionNodes = [];
        $this->classHtmlLoc = [];
        $this->functionHtmlLoc = [];
        $this->methodHtmlLoc = [];
        $this->currentFunctionName = [];
        $this->currentClassName = [];
        $this->currentMethodName = [];

        $loc = 0;
        $cloc = 0;
        $lloc = 0;

        if (null !== array_key_last($nodes)) {
            $lastNode = $nodes[array_key_last($nodes)];
            $loc = $lastNode->getEndLine();
        }

        if ($loc > 0) {
            $nodesWithoutHtml = array_filter($nodes, fn (Node $node) => !$node instanceof Node\Stmt\InlineHTML);

            $code = $this->prettyPrinter->prettyPrint($nodesWithoutHtml);

            [$cloc, $lloc] = $this->getClocAndLloc($code);
        }

        $fileMetricData = [
            MetricKey::LOC => $loc,
            MetricKey::CLOC => $cloc,
            MetricKey::LLOC => $lloc,
        ];

        $projectMetricKeys = [
            MetricKey::OVERALL_LOC => MetricKey::LOC,
            MetricKey::OVERALL_CLOC => MetricKey::CLOC,
            MetricKey::OVERALL_LLOC => MetricKey::LLOC,
        ];

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $fileMetricData
        );

        foreach ($projectMetricKeys as $projectMetricKey => $fileMetricKey) {
            $fileMetricValue = $fileMetricData[$fileMetricKey];

            $this->writer->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $projectMetricKey,
                function (float|int|null $value) use ($fileMetricValue): float|int {
                    $value ??= 0;

                    return $value + $fileMetricValue;
                }
            );
        }

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $fnName = (string) $node->namespacedName;

                $this->currentFunctionName[] = $fnName;
                $this->functionHtmlLoc[$fnName] = 0;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                if ('' === $className || '0' === $className) {
                    $className = 'anonymous@'.spl_object_hash($node);
                }

                $this->currentClassName[] = $className;
                $this->classHtmlLoc[$className] = 0;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $currentClassName = end($this->currentClassName);

                if (false === $currentClassName) {
                    break;
                }
                if (!isset($this->methodHtmlLoc[$currentClassName])) {
                    $this->methodHtmlLoc[$currentClassName] = [];
                }
                $this->methodHtmlLoc[$currentClassName][(string) $node->name] = 0;
                $this->currentMethodName[] = (string) $node->name;
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $this->handleFunctionNode($node);
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->handleClassNode($node);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->handleClassMethodNode($node);
                break;

            case $node instanceof Node\Stmt\InlineHTML:
                $this->countInlineHtml($node);
                break;

            case count($this->currentFunctionName) > 0 && str_starts_with($node->getType(), 'Stmt_'):
                $fnName = end($this->currentFunctionName);
                if ($node instanceof Node\Stmt) {
                    $this->functionNodes[$fnName][] = $node;
                }
                break;
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $metricValue = $this->reader->getMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            MetricKey::LLOC
        );
        $llocFile = null !== $metricValue ? $metricValue->asInt() : 0;

        $llocFileOutside = $llocFile - $this->insideLloc;

        $fileMetrics = [
            MetricKey::LLOC_OUTSIDE => $llocFileOutside,
            MetricKey::HTML_LOC => $this->fileHtmlLoc,
        ];

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $fileMetrics
        );

        $projectMetricIncrements = [
            MetricKey::OVERALL_LLOC_OUTSIDE => $fileMetrics[MetricKey::LLOC_OUTSIDE],
            MetricKey::OVERALL_INSIDE_METHOD_LLOC => $this->insideMethodLloc,
            MetricKey::OVERALL_INSIDE_FUNCTION_LLOC => $this->insideFunctionLloc,
            MetricKey::OVERALL_HTML_LOC => $this->fileHtmlLoc,
        ];

        foreach ($projectMetricIncrements as $key => $increment) {
            $this->writer->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $key,
                function (float|int|null $value) use ($increment): float|int {
                    $value ??= 0;

                    return $value + $increment;
                }
            );
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function getLinesOfCodeFunction(Node\Stmt\Function_ $node, string $functionName): array
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        // Get cloc and lloc from function body
        $fnNodes = $this->functionNodes[$functionName] ?? [];
        $functionBodyCode = $this->prettyPrinter->prettyPrint($fnNodes);
        [$cloc, $lloc] = $this->getClocAndLloc($functionBodyCode);

        // Get lloc of whole function (with function declaration and ending curly brackets
        $wholeFunction = $this->prettyPrinter->prettyPrint([$node]);
        [$wCloc, $wLloc] = $this->getClocAndLloc($wholeFunction);

        $this->insideLloc += $wLloc;
        $this->insideFunctionLloc += $wLloc;

        return [
            MetricKey::LOC => $loc,
            MetricKey::CLOC => $cloc,
            MetricKey::LLOC => $lloc,
        ];
    }

    /**
     * @return array{int, int}
     */
    private function getClocAndLloc(string $code): array
    {
        $cloc = 0;

        // count and remove multi lines comments
        if (preg_match_all('!/\*.*?\*/!s', $code, $matches)) {
            foreach ($matches[0] as $match) {
                $parts = preg_split('/\r\n|\r|\n/', $match);
                $cloc += max(1, is_array($parts) ? count($parts) : 1);
            }
        }
        $code = preg_replace('!/\*.*?\*/!s', '', $code);

        // count and remove single line comments
        $code = preg_replace_callback('!(\'[^\']*\'|"[^"]*")|((?:#|//).*$)!m', function (array $matches) use (&$cloc): string {
            if (isset($matches[2])) {
                ++$cloc;
            }

            return $matches[1];
        }, (string) $code, -1);

        $code = trim((string) preg_replace('!(^\s*[\r\n])!sm', '', (string) $code));

        $parts = preg_split('/\r\n|\r|\n/', $code);
        $lloc = is_array($parts) ? count($parts) : 0;

        return [$cloc, $lloc];
    }

    public function handleFunctionNode(Node\Stmt\Function_ $node): void
    {
        $functionName = array_pop($this->currentFunctionName);
        if (null === $functionName) {
            return;
        }

        $functionMetrics = $this->getLinesOfCodeFunction($node, $functionName);
        $functionMetrics[MetricKey::HTML_LOC] = $this->functionHtmlLoc[$functionName];
        $functionMetrics[MetricKey::START_LINE] = $node->getStartLine();
        $functionMetrics[MetricKey::END_LINE] = $node->getEndLine();

        $this->functionNodes[$functionName] = [];

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::FunctionCollection,
            [
                'path' => $this->path,
                'name' => $functionName,
            ],
            $functionMetrics
        );
    }

    public function handleClassNode(Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node): void
    {
        $className = array_pop($this->currentClassName);
        if (null === $className) {
            return;
        }

        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        $classCode = $this->prettyPrinter->prettyPrint([$node]);
        [$cloc, $lloc] = $this->getClocAndLloc($classCode);

        $classMetrics = [
            MetricKey::LOC => $loc,
            MetricKey::CLOC => $cloc,
            MetricKey::LLOC => $lloc,
            MetricKey::HTML_LOC => $this->classHtmlLoc[$className],
            MetricKey::START_LINE => $node->getStartLine(),
            MetricKey::END_LINE => $node->getEndLine(),
        ];

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            [
                'path' => $this->path,
                'name' => $className,
            ],
            $classMetrics
        );

        $this->insideLloc += $lloc;
        $this->insideMethodLloc += $lloc;
    }

    public function handleClassMethodNode(Node\Stmt\ClassMethod $node): void
    {
        $className = end($this->currentClassName);
        $methodName = array_pop($this->currentMethodName);
        if (false === $className || null === $methodName) {
            return;
        }

        $methodLoc = $node->getEndLine() - $node->getStartLine() + 1;
        $methodBodyNodes = $node->stmts ?: [];
        $methodBodyCode = $this->prettyPrinter->prettyPrint($methodBodyNodes);

        [$methodCloc, $methodLloc] = $this->getClocAndLloc($methodBodyCode);

        $methodMetrics = [
            MetricKey::LLOC => $methodLloc,
            MetricKey::CLOC => $methodCloc,
        ];

        if (0 === count($methodBodyNodes)) {
            $methodMetrics[MetricKey::LLOC] = 0;
            $methodMetrics[MetricKey::CLOC] = 0;
        }

        $methodMetrics[MetricKey::LOC] = $methodLoc;
        $methodMetrics[MetricKey::HTML_LOC] = $this->methodHtmlLoc[$className][$methodName];
        $methodMetrics[MetricKey::START_LINE] = $node->getStartLine();
        $methodMetrics[MetricKey::END_LINE] = $node->getEndLine();
        $methodMetrics[MetricKey::SOURCE_FILE] = $this->path;

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::MethodCollection,
            [
                'path' => $className,
                'name' => $methodName,
            ],
            $methodMetrics
        );
    }

    public function countInlineHtml(Node\Stmt\InlineHTML $node): void
    {
        $htmlLoc = $node->getEndLine() - $node->getStartLine();
        $this->fileHtmlLoc += $htmlLoc;

        if (count($this->currentClassName) > 0) {
            $className = end($this->currentClassName);
            $this->classHtmlLoc[$className] += $htmlLoc;

            if (count($this->currentMethodName)) {
                $this->methodHtmlLoc[$className][end($this->currentMethodName)] += $htmlLoc;
            }
        } elseif (count($this->currentFunctionName) > 0) {
            $this->functionHtmlLoc[end($this->currentFunctionName)] += $htmlLoc;
        }
    }
}
