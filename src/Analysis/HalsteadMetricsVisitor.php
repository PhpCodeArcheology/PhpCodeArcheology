<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class HalsteadMetricsVisitor implements NodeVisitor, VisitorInterface, PathAwareVisitorInterface
{
    use VisitorTrait;

    /**
     * @var string[]
     */
    private array $currentClassName = [];

    /**
     * @var string[]
     */
    private array $currentFunctionName = [];

    /** @var array<int, string> */
    private array $operators = [];

    /** @var array<int, mixed> */
    private array $operands = [];

    /** @var array<string, array<int, string>> */
    private array $classOperators = [];

    /** @var array<string, array<int, mixed>> */
    private array $classOperands = [];

    /** @var array<string, array<int, string>> */
    private array $functionOperators = [];

    /** @var array<string, array<int, mixed>> */
    private array $functionOperands = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->operators = [];
        $this->operands = [];
        $this->classOperators = [];
        $this->classOperands = [];
        $this->functionOperators = [];
        $this->functionOperands = [];
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

                $this->classOperators[$className] = [];
                $this->classOperands[$className] = [];

                $this->currentClassName[] = $className;
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $this->functionOperators[$functionName] = [];
                $this->functionOperands[$functionName] = [];

                $this->currentFunctionName[] = $functionName;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                if (false === $className) {
                    break;
                }
                $methodName = (string) $node->name;

                $key = sprintf('%s::%s', $className, $methodName);

                $this->functionOperators[$key] = [];
                $this->functionOperands[$key] = [];

                $this->currentFunctionName[] = $methodName;
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        $this->countOperators($node);
        $this->countOperands($node);

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->calculateMetrics($this->classOperators[$className] ?? [], $this->classOperands[$className] ?? [])
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);
                if (null === $functionName) {
                    break;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    $this->calculateMetrics($this->functionOperators[$functionName] ?? [], $this->functionOperands[$functionName] ?? [])
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);
                if (false === $className || null === $methodName) {
                    break;
                }

                $key = sprintf('%s::%s', $className, $methodName);

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    $this->calculateMetrics($this->functionOperators[$key] ?? [], $this->functionOperands[$key] ?? [])
                );
                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        // Calculate file metrics
        $halstead = $this->calculateMetrics($this->operators, $this->operands);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $halstead,
        );

        return null;
    }

    /**
     * @param array<int, string> $operators
     * @param array<int, mixed>  $operands
     *
     * @return array<string, int|float>
     */
    private function calculateMetrics(array $operators, array $operands): array
    {
        $uniqueOperators = array_map(unserialize(...), array_unique(array_map(serialize(...), $operators)));
        $uniqueOperands = array_map(unserialize(...), array_unique(array_map(serialize(...), $operands)));

        $n1 = count($uniqueOperators);
        $n2 = count($uniqueOperands);
        $N1 = count($operators);
        $N2 = count($operands);

        $n = $n1 + $n2;
        $N = $N1 + $N2;

        if (0 === $n2 || 0 === $N2) {
            return [
                MetricKey::VOCABULARY => $n,
                MetricKey::LENGTH => $N,
                MetricKey::CALC_LENGTH => 0,
                MetricKey::VOLUME => 0,
                MetricKey::DIFFICULTY => 0,
                MetricKey::EFFORT => 0,
                MetricKey::OPERATORS => $N1,
                MetricKey::OPERANDS => $N2,
                MetricKey::UNIQUE_OPERATORS => $n1,
                MetricKey::UNIQUE_OPERANDS => $n2,
                MetricKey::COMPLEXITY_DENSITY => 0,
            ];
        }

        $vocabulary = $n;
        $length = $N;
        $calculatedLength = $n * log($n, 2);
        $volume = $N * log($n, 2);
        $difficulty = ($n1 / 2) * ($N2 / $n2);
        $effort = $difficulty * $volume;

        /**
         * Special Php legacy analyzer metrics.
         *
         * $complexityDensity
         * This metric calculates the connection between the difficulty and size of the code and
         * resembles the complexity density. Higher values mean that the interaction between the building
         * blocks of the code is more complex.
         */
        $complexityDensity = $difficulty / ($vocabulary + $length);

        return [
            MetricKey::VOCABULARY => $vocabulary,
            MetricKey::LENGTH => $length,
            MetricKey::CALC_LENGTH => $calculatedLength,
            MetricKey::VOLUME => $volume,
            MetricKey::DIFFICULTY => $difficulty,
            MetricKey::EFFORT => $effort,
            MetricKey::OPERATORS => $N1,
            MetricKey::OPERANDS => $N2,
            MetricKey::UNIQUE_OPERATORS => $n1,
            MetricKey::UNIQUE_OPERANDS => $n2,
            MetricKey::COMPLEXITY_DENSITY => $complexityDensity,
        ];
    }

    public function afterSetPath(string $path): void
    {
        $this->operators = [];
        $this->operands = [];
    }

    private function countOperators(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Expr\BinaryOp:
            case $node instanceof Node\Expr\AssignOp:
            case $node instanceof Node\Stmt\If_:
            case $node instanceof Node\Stmt\ElseIf_:
            case $node instanceof Node\Stmt\Else_:
            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\Switch_:
            case $node instanceof Node\Expr\Match_:
            case $node instanceof Node\Stmt\Catch_:
            case $node instanceof Node\Stmt\Return_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
            case $node instanceof Node\Expr\Assign:
            case $node instanceof Node\Expr\Ternary:
            case $node instanceof Node\Expr\BooleanNot:
            case $node instanceof Node\Expr\BitwiseNot:
            case $node instanceof Node\Expr\FuncCall:
            case $node instanceof Node\Expr\MethodCall:
            case $node instanceof Node\Expr\StaticCall:
            case $node instanceof Node\Expr\New_:
            case $node instanceof Node\Expr\Instanceof_:
            case $node instanceof Node\Expr\UnaryMinus:
            case $node instanceof Node\Expr\UnaryPlus:
            case $node instanceof Node\Expr\PreDec:
            case $node instanceof Node\Expr\PreInc:
            case $node instanceof Node\Expr\PostDec:
            case $node instanceof Node\Expr\PostInc:
            case $node instanceof Node\Stmt\TryCatch:
            case $node instanceof Node\Expr\Throw_:
                $this->operators[] = $node::class;

                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);

                    $this->classOperators[$className][] = $node::class;

                    if (count($this->currentFunctionName) > 0) {
                        $methodName = end($this->currentFunctionName);
                        $key = sprintf('%s::%s', $className, $methodName);

                        if (isset($this->functionOperators[$key])) {
                            $this->functionOperators[$key][] = $node::class;
                        }
                    }
                } elseif (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionOperators[$functionName][] = $node::class;
                }
                break;
        }
    }

    private function countOperands(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Expr\Cast:
            case $node instanceof Node\Expr\Variable:
            case $node instanceof Node\Param:
            case $node instanceof Node\Scalar:
                if (isset($node->value)) {
                    $name = $node->value;
                } elseif (isset($node->name)) {
                    $name = $node->name;
                } else {
                    $name = $node::class;
                }

                $this->operands[] = $name;

                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);

                    $this->classOperands[$className][] = $name;

                    if (count($this->currentFunctionName) > 0) {
                        $methodName = end($this->currentFunctionName);
                        $key = sprintf('%s::%s', $className, $methodName);

                        if (isset($this->functionOperands[$key])) {
                            $this->functionOperands[$key][] = $name;
                        }
                    }
                } elseif (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionOperands[$functionName][] = $name;
                }
                break;
        }
    }
}
