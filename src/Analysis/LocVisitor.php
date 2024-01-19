<?php /** @noinspection ALL */

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsFactory;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * @var string[]
     */
    private array $currentFunctionName = [];

    /**
     * @var string[]
     */
    private array $currentClassName = [];

    /**
     * @var string[]
     */
    private array $currentMethodName = [];

    /**
     * @var Node[]
     */
    private array $functionNodes = [];

    /**
     * @var int
     */
    private int $insideLloc = 0;

    /**
     * @var int
     */
    private int $insideFunctionLloc = 0;

    /**
     * @var int
     */
    private int $insideMethodLloc = 0;

    /**
     * @var int
     */
    private int $fileHtmlLoc = 0;

    /**
     * @var int[]
     */
    private array $classHtmlLoc = [];

    /**
     * @var int[]
     */
    private array $functionHtmlLoc = [];

    /**
     * @var int[]
     */
    private array $methodHtmlLoc = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->insideLloc = 0;
        $this->fileHtmlLoc = 0;

        $loc = 0;
        $cloc = 0;
        $lloc = 0;

        if (array_key_last($nodes) !== null) {
            $lastNode = $nodes[array_key_last($nodes)];
            $loc = $lastNode ? $lastNode->getEndLine() : 0;
        }

        if ($loc > 0) {
            $nodesWithoutHtml = array_filter($nodes, function($node) {
                return !$node instanceof Node\Stmt\InlineHTML;
            });

            $prettyPrinter = new PrettyPrinter\Standard();
            $code = $prettyPrinter->prettyPrint($nodesWithoutHtml);

            [$cloc, $lloc] = $this->getClocAndLloc($code);
        }

        $fileMetricData = [
            'loc' => $loc,
            'cloc' => $cloc,
            'lloc' => $lloc,
        ];

        $projectMetricKeys = [
            'overallLoc' => 'loc',
            'overallCloc' => 'cloc',
            'overallLloc' => 'lloc',
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $fileMetricData
        );

        foreach ($projectMetricKeys as $projectMetricKey => $fileMetricKey) {
            $fileMetricValue = $fileMetricData[$fileMetricKey];

            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $projectMetricKey,
                function ($value) use ($fileMetricValue) {
                    $value = $value ?? 0;
                    return $value + $fileMetricValue;
                }
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
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
                if (! $className) {
                    $className = 'anonymous@' . spl_object_hash($node);
                }

                $this->currentClassName[] = $className;
                $this->classHtmlLoc[$className] = 0;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $currentClassName = end($this->currentClassName);

                if (! isset($this->methodHtmlLoc[$currentClassName])) {
                    $this->methodHtmlLoc[$currentClassName] = [];
                }
                $this->methodHtmlLoc[$currentClassName][(string) $node->name] = 0;
                $this->currentMethodName[] = (string) $node->name;
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case$node instanceof Node\Stmt\Function_:
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
                $this->functionNodes[end($this->currentFunctionName)][] = $node;
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $llocFile = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            'lloc'
        )->getValue();

        $llocFileOutside = $llocFile - $this->insideLloc;

        $fileMetrics = [
            'llocOutside' => $llocFileOutside,
            'htmlLoc' => $this->fileHtmlLoc,
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $fileMetrics
        );

        $projectMetricIncrements = [
            'overallLlocOutside' => $fileMetrics['llocOutside'],
            'overallInsideMethodLloc' => $this->insideMethodLloc,
            'overallInsideFuntionLloc' => $this->insideFunctionLloc,
            'overallHtmlLoc' => $this->fileHtmlLoc,
        ];

        foreach ($projectMetricIncrements as $key => $increment) {
            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $key,
                function($value) use ($increment) {
                    $value = $value ?? 0;
                    return $value + $increment;
                }
            );
        }
    }

    private function getLinesOfCodeFunction(Node\Stmt\Function_ $node, string $functionName): array
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        // Get cloc and lloc from function body
        $prettyPrinter = new PrettyPrinter\Standard();

        $fnNodes = $this->functionNodes[$functionName] ?? [];
        $functionBodyCode = $prettyPrinter->prettyPrint($fnNodes);
        [$cloc, $lloc] = $this->getClocAndLloc($functionBodyCode);

        // Get lloc of whole function (with function declaration and ending curly brackets
        $wholeFunction = $prettyPrinter->prettyPrint([$node]);
        [$wCloc, $wLloc] = $this->getClocAndLloc($wholeFunction);

        $this->insideLloc += $wLloc;
        $this->insideFunctionLloc += $wLloc;

        return [
            'loc' => $loc,
            'cloc' => $cloc,
            'lloc' => $lloc,
        ];
    }

    private function getClocAndLloc(string $code): array
    {
        $cloc = 0;

        // count and remove multi lines comments
        if (preg_match_all('!/\*.*?\*/!s', $code, $matches)) {
            foreach ($matches[0] as $match) {
                $cloc += max(1, count(preg_split('/\r\n|\r|\n/', $match)));
            }
        }
        $code = preg_replace('!/\*.*?\*/!s', '', $code);

        // count and remove single line comments
        $code = preg_replace_callback('!(\'[^\']*\'|"[^"]*")|((?:#|//).*$)!m', function (array $matches) use (&$cloc) {
            if (isset($matches[2])) {
                $cloc += 1;
            }
            return $matches[1];
        }, $code, -1);

        $code = trim(preg_replace('!(^\s*[\r\n])!sm', '', $code));

        $lloc = count(preg_split('/\r\n|\r|\n/', $code));

        return [$cloc, $lloc];
    }

    /**
     * @param Node\Stmt\Function_ $node
     * @return void
     */
    public function handleFunctionNode(Node\Stmt\Function_ $node): void
    {
        $functionName = array_pop($this->currentFunctionName);

        $functionMetrics = $this->getLinesOfCodeFunction($node, $functionName);
        $functionMetrics['htmlLoc'] = $this->functionHtmlLoc[$functionName];

        $this->functionNodes[$functionName] = [];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FunctionCollection,
            [
                'path' => $this->path,
                'name' => $functionName,
            ],
            $functionMetrics
        );
    }

    /**
     * @param Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node
     * @return void
     */
    public function handleClassNode(Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node): void
    {
        $className = array_pop($this->currentClassName);

        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        $prettyPrinter = new PrettyPrinter\Standard();
        $classCode = $prettyPrinter->prettyPrint([$node]);
        [$cloc, $lloc] = $this->getClocAndLloc($classCode);

        $classMetrics = [
            'loc' => $loc,
            'cloc' => $cloc,
            'lloc' => $lloc,
            'htmlLoc' => $this->classHtmlLoc[$className],
        ];

        $this->metricsController->setMetricValues(
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

    /**
     * @param Node\Stmt\ClassMethod $node
     * @return void
     */
    public function handleClassMethodNode(Node\Stmt\ClassMethod $node): void
    {
        $className = end($this->currentClassName);
        $methodName = array_pop($this->currentMethodName);

        $prettyPrinter = new PrettyPrinter\Standard();

        $methodLoc = $node->getEndLine() - $node->getStartLine() + 1;
        $methodBodyNodes = $node->stmts ? $node->stmts : [];
        $methodBodyCode = $prettyPrinter->prettyPrint($methodBodyNodes);

        [$methodLloc, $methodCloc] = $this->getClocAndLloc($methodBodyCode);

        $methodMetrics = [
            'lloc' => $methodLloc,
            'cloc' => $methodCloc,
        ];

        if (count($methodBodyNodes) === 0) {
            $methodMetrics['lloc'] = 0;
            $methodMetrics['cloc'] = 0;
        }

        $methodMetrics['loc'] = $methodLoc;
        $methodMetrics['htmlLoc'] = $this->methodHtmlLoc[$className][$methodName];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::MethodCollection,
            [
                'path' => $className,
                'name' => $methodName,
            ],
            $methodMetrics
        );
    }

    /**
     * @param Node\Stmt\InlineHTML $node
     * @return void
     */
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
