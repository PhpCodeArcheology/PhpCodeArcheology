<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Calculate cyclomatic complexity.
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
     * @var array<int, string>
     */
    private array $currentClassName = [];

    /**
     * @var array<int, string>
     */
    private array $currentFunctionName = [];

    private int $fileCc = 1;

    /** @var array<string, int> */
    private array $classCc = [];

    /** @var array<string, int> */
    private array $functionCc = [];

    /** @var array<string, array<string, int>> */
    private array $methodCc = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->fileCc = 1;
        $this->classCc = [];
        $this->functionCc = [];
        $this->methodCc = [];
        $this->currentClassName = [];
        $this->currentFunctionName = [];

        return null;
    }

    public function enterNode(Node $node): int|Node|null
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
                if (false === $className) {
                    break;
                }
                $methodName = (string) $node->name;

                $this->currentFunctionName[] = $methodName;
                $this->methodCc[$className][$methodName] = 1;
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $this->currentFunctionName[] = $functionName;
                $this->functionCc[$functionName] = 1;
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $this->writer->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->classCc[$className] ?? 1,
                    MetricKey::CC
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);
                if (false === $className || null === $methodName) {
                    break;
                }

                $this->writer->setMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    $this->methodCc[$className][$methodName] ?? 1,
                    MetricKey::CC
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);
                if (null === $functionName) {
                    break;
                }

                $this->writer->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    $this->functionCc[$functionName] ?? 1,
                    MetricKey::CC
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
                        if (!isset($this->methodCc[$className][$methodName])) {
                            $this->methodCc[$className][$methodName] = 1;
                        }
                        $this->methodCc[$className][$methodName] += $increase;
                    }

                    break;
                }

                if (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionCc[$functionName] += $increase;
                }
                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        $this->writer->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->fileCc,
            MetricKey::CC
        );

        return null;
    }

    /**
     * Actual calculation of complexity.
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
                if ($node->cond instanceof Node\Expr) {
                    $inc = 1;
                }
                break;

            case $node instanceof Node\Expr\BinaryOp\Spaceship:
                $inc = 1;
                break;
        }

        return $inc;
    }
}
