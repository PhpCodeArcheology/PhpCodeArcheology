<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Calculate cyclomatic complexity
 *
 * Formula:
 *
 * M = E - N + 2P
 *
 * M = Cyclomatic complexity
 * E = Number of edges in the graph
 * N = Number of nodes
 * P = Number of connected components
 *
 * Interpretation
 * 1 - 10 Simple procedure, little risk
 * 11 - 20 More complex, moderate risk
 * 21 - 50 Complex, high risk
 * > 50 Untestable code, very high risk
 *
 * @see https://en.wikipedia.org/wiki/Cyclomatic_complexity
 */

class CyclomaticComplexityVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * @var ClassMetricsCollection[]
     */
    private array $currentClass = [];

    /**
     * @var FunctionMetricsCollection[]
     */
    private array $currentFunction = [];

    /**
     * @var int
     */
    private int $fileCc = 1;

    /**
     * @var array
     */
    private array $classCc = [];

    /**
     * @var array
     */
    private array $functionCc = [];

    /**
     * @param Node[] $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->getFileMetrics();
        $this->fileCc = 1;
    }

    /**
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                    $this->metrics,
                    $node,
                    $this->path
                );

                $this->currentClass[] = $classMetrics;
                $this->classCc[$classMetrics->getName()] = 1;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $currentClass = end($this->currentClass);
                $methods = $currentClass->get('methods');

                $methodMetric = FunctionMetricsFactory::createFromMethodsByNameAndClassMetrics(
                    $methods,
                    $node->name,
                    $currentClass
                );
                $methodName = $methodMetric->getName();

                $this->currentFunction[] = $methodMetric;
                $this->functionCc[$currentClass->getName()][$methodName] = 1;
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                    $this->metrics,
                    $node->namespacedName,
                    $this->path
                );

                $this->currentFunction[] = $functionMetrics;
                $this->functionCc[$functionMetrics->getName()] = 1;
                break;
        }
    }

    /**
     * @param Node $node
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $currentClass = array_pop($this->currentClass);

                $metricValue = MetricValue::ofValueAndType(
                    value: $this->classCc[$currentClass->getName()],
                    type: $this->usedMetricTypes['cc'],
                );
                $currentClass->set('cc', $metricValue);

                $this->metrics->set((string) $currentClass->getIdentifier(), $currentClass);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $currentClass = end($this->currentClass);
                $currentMethod = array_pop($this->currentFunction);
                $methods = $currentClass->get('methods');

                $metricValue = MetricValue::ofValueAndType(
                    value: $this->functionCc[$currentClass->getName()][$currentMethod->getName()],
                    type: $this->usedMetricTypes['cc'],
                );
                $currentMethod->set('cc', $metricValue);

                $methods[(string) $currentMethod->getIdentifier()] = $currentMethod;
                $this->metrics->set((string) $currentClass->getIdentifier(), $currentClass);
                break;

            case $node instanceof Node\Stmt\Function_:
                $currentFunction = array_pop($this->currentFunction);

                $metricValue = MetricValue::ofValueAndType(
                    value: $this->functionCc[$currentFunction->getName()],
                    type: $this->usedMetricTypes['cc'],
                );
                $currentFunction->set('cc', $metricValue);

                $this->metrics->set((string) $currentFunction->getIdentifier(), $currentFunction);
                break;

            default:
                $increase = $this->getIncreaseForNode($node);

                $this->fileCc += $increase;

                if (count($this->currentClass) > 0) {
                    $currentClass = end($this->currentClass);
                    $this->classCc[$currentClass->getName()] += $increase;

                    if (count($this->currentFunction) > 0) {
                        $currentFunction = end($this->currentFunction);
                        if (!isset($this->functionCc[$currentClass->getName()][$currentFunction->getName()])) {
                            $this->functionCc[$currentClass->getName()][$currentFunction->getName()] = 1;
                        }
                        $this->functionCc[$currentClass->getName()][$currentFunction->getName()] += $increase;
                    }

                    break;
                }

                if (count($this->currentFunction) > 0) {
                    $currentFunction = end($this->currentFunction);
                    $this->functionCc[$currentFunction->getName()] += $increase;
                }
                break;
        }
    }

    /**
     * @param Node[] $nodes
     * @return void
     */
    public function afterTraverse(array $nodes): void
    {
        $metricValue = MetricValue::ofValueAndType(
            value: $this->fileCc,
            type: $this->usedMetricTypes['cc'],
        );
        $this->fileMetrics->set('cc', $metricValue);

        $this->metrics->set((string) $this->fileMetrics->getIdentifier(), $this->fileMetrics);
    }

    /**
     * Actual calculation of complexity
     *
     * @param Node $node
     * @return int
     */
    private function getIncreaseForNode(Node $node): int
    {
        $inc = 0;

        switch (true) {
            case $node instanceof Node\Stmt\If_:
            case $node instanceof Node\Stmt\ElseIf_:
            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
            case $node instanceof Node\Stmt\Catch_:
            case $node instanceof Node\Expr\Ternary:
            case $node instanceof Node\Expr\BinaryOp\Coalesce:
                $inc = 1;
                break;

            case $node instanceof Node\Stmt\Case_:
            case $node instanceof Node\Expr\Match_:
                if ($node->cond !== null) {
                    $inc = 1;
                }
                break;

            case $node instanceof Node\Expr\BinaryOp\Spaceship:
                $inc = 2;
                break;

        }

        return $inc;
    }
}
