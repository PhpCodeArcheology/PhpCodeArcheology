<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Manager\MetricValue;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $functionNodes = [];

    /**
     * @var FunctionMetrics[]
     */
    private array $currentFunction = [];

    /**
     * @var ClassMetrics[]
     */
    private array $currentClass = [];

    /**
     * @var string[]
     */
    private array $currentMethodName = [];

    private int $insideLloc = 0;

    private int $insideFunctionLloc = 0;

    private int $insideMethodLloc = 0;

    private int $fileHtmlLoc = 0;

    private array $classHtmlLoc = [];

    private array $functionHtmlLoc = [];

    private array $methodHtmlLoc = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->projectMetrics = $this->metrics->get('project');

        $this->getFileMetrics();

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

        $this->setMetricValues($this->fileMetrics, [
            'loc' => $loc,
            'cloc' => $cloc,
            'lloc' => $lloc,
        ]);

        $projectLoc = $this->projectMetrics->get('OverallLoc') + $loc;
        $projectCloc = $this->projectMetrics->get('OverallCloc') + $cloc;
        $projectLloc = $this->projectMetrics->get('OverallLloc') + $lloc;

        $this->projectMetrics->set('OverallLoc', $projectLoc);
        $this->projectMetrics->set('OverallCloc', $projectCloc);
        $this->projectMetrics->set('OverallLloc', $projectLloc);
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_) {

            $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                $this->metrics,
                $node->namespacedName,
                $this->path
            );

            $this->currentFunction[] = $functionMetrics;
            $this->functionHtmlLoc[$functionMetrics->getName()] = 0;
        }
        if ($node instanceof Node\Stmt\ClassMethod) {
            $currentClass = end($this->currentClass);

            if (! isset($this->methodHtmlLoc[$currentClass->getName()])) {
                $this->methodHtmlLoc[$currentClass->getName()] = [];
            }
            $this->methodHtmlLoc[$currentClass->getName()][(string) $node->name] = 0;
            $this->currentMethodName[] = (string) $node->name;
        }
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Enum_) {

            $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                $this->metrics,
                $node,
                $this->path
            );

            $this->currentClass[] = $classMetrics;
            $this->classHtmlLoc[$classMetrics->getName()] = 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_) {

            $functionMetrics = array_pop($this->currentFunction);
            $this->getLinesOfCodeFunction($node, $functionMetrics->getName());
            $this->functionNodes[$functionMetrics->getName()] = [];

            $functionId = (string) $functionMetrics->getIdentifier();

            $this->setMetricValue(
                $functionMetrics,
                'htmlLoc',
                $this->functionHtmlLoc[$functionMetrics->getName()]
            );

            $this->metrics->set($functionId, $functionMetrics);

        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $classMetrics = array_pop($this->currentClass);

            $className = $classMetrics->getName();
            $classId = (string) $classMetrics->getIdentifier();

            $loc = $node->getEndLine() - $node->getStartLine() + 1;

            $prettyPrinter = new PrettyPrinter\Standard();
            $classCode = $prettyPrinter->prettyPrint([$node]);
            [$cloc, $lloc] = $this->getClocAndLloc($classCode);

            $this->setMetricValues($classMetrics, [
                'loc' => $loc,
                'cloc' => $cloc,
                'lloc' => $lloc,
                'htmlLoc' => $this->classHtmlLoc[$className],
            ]);

            $this->metrics->set($classId, $classMetrics);

            $this->insideLloc += $lloc;
            $this->insideMethodLloc += $lloc;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $prettyPrinter = new PrettyPrinter\Standard();

            $classMetrics = end($this->currentClass);
            $methods = $classMetrics->get('methods');

            $methodName = array_pop($this->currentMethodName);
            $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath($methodName, (string) $classMetrics->getIdentifier());
            $methodMetric = $methods[$methodId];

            $methodLoc = $node->getEndLine() - $node->getStartLine() + 1;
            $methodBodyNodes = $node->stmts ? $node->stmts : [];
            $methodBodyCode = $prettyPrinter->prettyPrint($methodBodyNodes);
            [$methodCloc, $methodLloc] = $this->getClocAndLloc($methodBodyCode);

            if (count($methodBodyNodes) === 0) {
                $methodLloc = 0;
                $methodCloc = 0;
            }

            $this->setMetricValues($classMetrics, [
                'loc' => $methodLoc,
                'cloc' => $methodCloc,
                'lloc' => $methodLloc,
                'htmlLoc' => $this->methodHtmlLoc[$classMetrics->getName()][$methodMetric->getName()],
            ]);

            $methods[$methodId] = $methodMetric;

            $classMetrics->set('methods', $methods);
            $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
        }
        elseif ($node instanceof Node\Stmt\InlineHTML) {
            $htmlLoc = $node->getEndLine() - $node->getStartLine();
            $this->fileHtmlLoc += $htmlLoc;

            if (count($this->currentClass) > 0) {
                $currentClass = end($this->currentClass);
                $className = $currentClass->getName();
                $this->classHtmlLoc[$className] += $htmlLoc;

                if (count($this->currentMethodName)) {

                    $this->methodHtmlLoc[$currentClass->getName()][end($this->currentMethodName)] += $htmlLoc;
                }
            }
            elseif (count($this->currentFunction) > 0) {
                $this->functionHtmlLoc[end($this->currentFunction)->getName()] += $htmlLoc;
            }
        }
        elseif (count($this->currentFunction) > 0 && str_starts_with($node->getType(), 'Stmt_')) {
            $this->functionNodes[end($this->currentFunction)->getName()][] = $node;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileId = (string) $this->fileMetrics->getIdentifier();

        $llocFile = $this->fileMetrics->get('lloc')->getValue();
        $llocFileOutside = $llocFile - $this->insideLloc;

        $this->setMetricValues($this->fileMetrics, [
            'llocOutside' => $llocFileOutside,
            'htmlLoc' => $this->fileHtmlLoc,
        ]);

        $this->metrics->set($fileId, $this->fileMetrics);
        $this->metrics->set('project', $this->projectMetrics);

        $OverallLlocOutside = $this->projectMetrics->get('OverallLlocOutside');
        $OverallInsideMethodLloc = $this->projectMetrics->get('OverallInsideMethodLloc');
        $OverallInsideFuntionLloc = $this->projectMetrics->get('OverallInsideFuntionLloc');
        $OverallHtmlLoc = $this->projectMetrics->get('OverallHtmlLoc') ?? 0;

        $OverallLlocOutside += $llocFileOutside;
        $OverallInsideMethodLloc += $this->insideMethodLloc;
        $OverallInsideFuntionLloc += $this->insideFunctionLloc;
        $OverallHtmlLoc += $this->fileHtmlLoc;

        $this->projectMetrics->set('OverallLlocOutside', $OverallLlocOutside);
        $this->projectMetrics->set('OverallInsideMethodLloc', $OverallInsideMethodLloc);
        $this->projectMetrics->set('OverallInsideFuntionLloc', $OverallInsideFuntionLloc);
        $this->projectMetrics->set('OverallHtmlLoc', $OverallHtmlLoc);
    }

    private function getLinesOfCodeFunction(Node\Stmt\Function_ $node, string $functionName): void
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        // Get cloc and lloc from function body
        $prettyPrinter = new PrettyPrinter\Standard();

        $fnNodes = $this->functionNodes[$functionName] ?? [];
        $functionBodyCode = $prettyPrinter->prettyPrint($fnNodes);
        [$cloc, $lloc] = $this->getClocAndLloc($functionBodyCode);

        $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
            $this->metrics,
            $node->namespacedName,
            $this->path
        );

        $this->setMetricValues($functionMetrics, [
            'loc' => $loc,
            'cloc' => $cloc,
            'lloc' => $lloc,
        ]);

        $this->metrics->push($functionMetrics);

        // Get lloc of whole function (with function declaration and ending curly brackets
        $wholeFunction = $prettyPrinter->prettyPrint([$node]);
        [$wCloc, $wLloc] = $this->getClocAndLloc($wholeFunction);

        $this->insideLloc += $wLloc;
        $this->insideFunctionLloc += $wLloc;
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
}
