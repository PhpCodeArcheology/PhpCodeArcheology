<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
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
     * @var string[]
     */
    public array $currentClassName = [];

    /**
     * @var string[]
     */
    public array $currentFunctionName = [];

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
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->classCc[$className] = 1;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = (string) $node->name;

                $this->currentFunctionName[] = $methodName;
                $this->functionCc[$className][$methodName] = 1;
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $this->currentFunctionName[] = $functionName;
                $this->functionCc[$functionName] = 1;
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
                $className = array_pop($this->currentClassName);

                $this->repository->saveMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->classCc[$className],
                    'cc'
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);

                $this->repository->saveMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    $this->functionCc[$className][$methodName],
                    'cc'
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);

                $this->repository->saveMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    $this->functionCc[$functionName],
                    'cc'
                );
                break;

            default:
                $increase = $this->getIncreaseForNode($node);

                $this->fileCc += $increase;

                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);
                    $this->classCc[$className] += $increase;

                    if (count($this->currentFunctionName) > 0) {
                        $methodName = end($this->currentFunctionName);
                        if (!isset($this->functionCc[$className][$methodName])) {
                            $this->functionCc[$className][$methodName] = 1;
                        }
                        $this->functionCc[$className][$methodName] += $increase;
                    }

                    break;
                }

                if (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionCc[$functionName] += $increase;
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
        $this->repository->saveMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->fileCc,
            'cc'
        );
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
