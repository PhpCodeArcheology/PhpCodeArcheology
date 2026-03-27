<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Calculate cognitive complexity (SonarSource algorithm).
 *
 * Unlike cyclomatic complexity, cognitive complexity weights:
 * - Nesting depth (deeper = harder to understand)
 * - Boolean operator sequences (mixed operators are harder)
 * - Structural complexity (if/for/while etc.)
 *
 * @see https://www.sonarsource.com/docs/CognitiveComplexity.pdf
 */
class CognitiveComplexityVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $currentClassName = [];
    /** @var array<int, string> */
    private array $currentFunctionName = [];

    private int $fileCogC = 0;
    /** @var array<string, int> */
    private array $classCogC = [];
    /** @var array<string, int> */
    private array $functionCogC = [];
    /** @var array<string, array<string, int>> */
    private array $methodCogC = [];

    private int $nestingLevel = 0;
    /** @var array<int, string> */
    private array $nestingStack = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->fileCogC = 0;
        $this->classCogC = [];
        $this->functionCogC = [];
        $this->methodCogC = [];
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->nestingLevel = 0;
        $this->nestingStack = [];

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
                $this->classCogC[$className] = 0;

                // Anonymous classes increase nesting
                if ($node instanceof Node\Stmt\Class_ && !$node->name instanceof Node\Identifier) {
                    ++$this->nestingLevel;
                    $this->nestingStack[] = 'anon-class';
                }
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                if (false === $className) {
                    break;
                }
                $methodName = (string) $node->name;
                $this->currentFunctionName[] = $methodName;
                $this->methodCogC[$className][$methodName] = 0;
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;
                $this->currentFunctionName[] = $functionName;
                $this->functionCogC[$functionName] = 0;
                break;

            case $node instanceof Node\Expr\Closure:
            case $node instanceof Node\Expr\ArrowFunction:
                $this->nestingLevel++;
                $this->nestingStack[] = 'closure';
                break;

                // Structural increments WITH nesting penalty
            case $node instanceof Node\Stmt\If_:
                $this->addIncrement(1 + $this->nestingLevel);
                ++$this->nestingLevel;
                $this->nestingStack[] = 'if';
                break;

            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
                $this->addIncrement(1 + $this->nestingLevel);
                ++$this->nestingLevel;
                $this->nestingStack[] = 'loop';
                break;

            case $node instanceof Node\Stmt\Catch_:
                $this->addIncrement(1 + $this->nestingLevel);
                ++$this->nestingLevel;
                $this->nestingStack[] = 'catch';
                break;

            case $node instanceof Node\Stmt\Switch_:
            case $node instanceof Node\Expr\Match_:
                $this->addIncrement(1 + $this->nestingLevel);
                ++$this->nestingLevel;
                $this->nestingStack[] = 'switch';
                break;

                // Structural increments WITHOUT nesting penalty
            case $node instanceof Node\Stmt\ElseIf_:
            case $node instanceof Node\Stmt\Else_:
            case $node instanceof Node\Expr\BinaryOp\Coalesce:
                $this->addIncrement(1);
                break;
                // Ternary and null coalesce: structural with nesting
            case $node instanceof Node\Expr\Ternary:
                $this->addIncrement(1 + $this->nestingLevel);
                break;

                // Boolean operators: +1 only on operator change
            case $node instanceof Node\Expr\BinaryOp\BooleanAnd:
            case $node instanceof Node\Expr\BinaryOp\LogicalAnd:
                $this->handleBooleanOperator($node, 'and');
                break;

            case $node instanceof Node\Expr\BinaryOp\BooleanOr:
            case $node instanceof Node\Expr\BinaryOp\LogicalOr:
                $this->handleBooleanOperator($node, 'or');
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
                if ($node instanceof Node\Stmt\Class_ && !$node->name instanceof Node\Identifier) {
                    --$this->nestingLevel;
                    array_pop($this->nestingStack);
                }

                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    $this->classCogC[$className] ?? 0,
                    MetricKey::COGNITIVE_COMPLEXITY
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);
                if (false === $className || null === $methodName) {
                    break;
                }
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $className, 'name' => $methodName],
                    $this->methodCogC[$className][$methodName] ?? 0,
                    MetricKey::COGNITIVE_COMPLEXITY
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);
                if (null === $functionName) {
                    break;
                }
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $functionName],
                    $this->functionCogC[$functionName] ?? 0,
                    MetricKey::COGNITIVE_COMPLEXITY
                );
                break;

            case $node instanceof Node\Expr\Closure:
            case $node instanceof Node\Expr\ArrowFunction:
            case $node instanceof Node\Stmt\If_:
            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
            case $node instanceof Node\Stmt\Catch_:
            case $node instanceof Node\Stmt\Switch_:
                // Match_ is an expression, handle separately
            case $node instanceof Node\Expr\Match_:
                $this->nestingLevel--;
                array_pop($this->nestingStack);
                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->fileCogC,
            MetricKey::COGNITIVE_COMPLEXITY
        );

        return null;
    }

    private function addIncrement(int $amount): void
    {
        $this->fileCogC += $amount;

        if (count($this->currentClassName) > 0) {
            $className = end($this->currentClassName);
            $this->classCogC[$className] += $amount;

            if (count($this->currentFunctionName) > 0) {
                $methodName = end($this->currentFunctionName);
                if (!isset($this->methodCogC[$className][$methodName])) {
                    $this->methodCogC[$className][$methodName] = 0;
                }
                $this->methodCogC[$className][$methodName] += $amount;
            }

            return;
        }

        if (count($this->currentFunctionName) > 0) {
            $functionName = end($this->currentFunctionName);
            $this->functionCogC[$functionName] += $amount;
        }
    }

    /**
     * @param 'and'|'or' $type
     */
    private function handleBooleanOperator(Node\Expr\BinaryOp $node, string $type): void
    {
        // Only increment at the START of a boolean sequence.
        // `a && b && c` → tree: &&(&&(a,b),c) → outer's left IS same type → skip; inner's left is NOT → +1 → total 1
        // `a && b || c` → tree: ||(&&(a,b),c) → ||'s left is && (diff) → +1; &&'s left is a → +1 → total 2
        $left = $node->left;

        $leftIsSameType = match ($type) {
            'and' => $left instanceof Node\Expr\BinaryOp\BooleanAnd || $left instanceof Node\Expr\BinaryOp\LogicalAnd,
            'or' => $left instanceof Node\Expr\BinaryOp\BooleanOr || $left instanceof Node\Expr\BinaryOp\LogicalOr,
        };

        if (!$leftIsSameType) {
            $this->addIncrement(1);
        }
    }
}
