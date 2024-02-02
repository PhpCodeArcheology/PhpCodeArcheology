<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class HalsteadMetricsVisitor implements NodeVisitor, VisitorInterface
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

    private array $operators = [];

    private array $operands = [];

    private array $classOperators = [];

    private array $classOperands = [];

    private array $functionOperators = [];

    private array $functionOperands = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        // TODO: Implement beforeTraverse() method.
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
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

            case $node instanceof Node\Stmt\Function_:#
                $functionName = (string) $node->namespacedName;

                $this->functionOperators[$functionName] = [];
                $this->functionOperands[$functionName] = [];

                $this->currentFunctionName[] = $functionName;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = (string) $node->name;

                $key = sprintf('%s::%s', $className, $methodName);

                $this->functionOperators[$key] = [];
                $this->functionOperands[$key] = [];

                $this->currentFunctionName[] = $methodName;
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        $this->countOperators($node);
        $this->countOperands($node);

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->calculateMetrics($this->classOperators[$className], $this->classOperands[$className])
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    $this->calculateMetrics($this->functionOperators[$functionName], $this->functionOperands[$functionName])
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);

                $key = sprintf('%s::%s', $className, $methodName);

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    $this->calculateMetrics($this->functionOperators[$key], $this->functionOperands[$key])
                );
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        // Calculate file metrics
        $halstead = $this->calculateMetrics($this->operators, $this->operands);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $halstead,
        );
    }

    private function calculateMetrics(array $operators, array $operands): array
    {
        $uniqueOperators = array_map('unserialize', array_unique(array_map('serialize', $operators)));
        $uniqueOperands = array_map('unserialize', array_unique(array_map('serialize', $operands)));

        $n1 = count($uniqueOperators);
        $n2 = count($uniqueOperands);
        $N1 = count($operators);
        $N2 = count($operands);

        $n = $n1 + $n2;
        $N = $N1 + $N2;

        if ($n2 === 0 || $N2 === 0) {
            return [
                'vocabulary' => $n,
                'length' => $N,
                'calcLength' => 0,
                'volume' => 0,
                'difficulty' => 0,
                'effort' => 0,
                'operators' => $N1,
                'operands' => $N2,
                'uniqueOperators' => $n1,
                'uniqueOperands' => $n2,
                'complexityDensity' => 0,
            ];
        }

        $vocabulary = $n;
        $length = $N;
        $calculatedLength = $n * log($n, 2);
        $volume = $N * log($n, 2);
        $difficulty = ($n1 / 2) * ($N2 / $n2);
        $effort = $difficulty * $volume;

        /**
         * Special Php legacy analyzer metrics
         *
         * $complexityDensity
         * This metric calculates the connection between the difficulty and size of the code and
         * resembles the complexity density. Higher values mean that the interaction between the building
         * blocks of the code is more complex.
         */
        $complexityDensity = $difficulty / ($vocabulary + $length);

        return [
            'vocabulary' => $vocabulary,
            'length' => $length,
            'calcLength' => $calculatedLength,
            'volume' => $volume,
            'difficulty' => $difficulty,
            'effort' => $effort,
            'operators' => $N1,
            'operands' => $N2,
            'uniqueOperators' => $n1,
            'uniqueOperands' => $n2,
            'complexityDensity' => $complexityDensity,
        ];
    }

    private function afterSetPath(): void
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
            case $node instanceof Node\Stmt\Throw_:
                $this->operators[] = get_class($node);

                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);

                    $this->classOperators[$className][] = get_class($node);

                    if (count($this->currentFunctionName) > 0) {
                        $methodName = end($this->currentFunctionName);
                        $key = sprintf('%s::%s', $className, $methodName);

                        if (isset ($this->functionOperators[$key])) {
                            $this->functionOperators[$key][] = get_class($node);
                        }
                    }
                }
                elseif (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionOperators[$functionName][] = get_class($node);
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
                }
                elseif (isset($node->name)) {
                    $name = $node->name;
                }
                else {
                    $name = get_class($node);
                }

                $this->operands[] = $name;

                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);

                    $this->classOperands[$className][] = $name;

                    if (count($this->currentFunctionName) > 0) {
                        $methodName = end($this->currentFunctionName);
                        $key = sprintf('%s::%s', $className, $methodName);

                        if (isset ($this->functionOperands[$key])) {
                            $this->functionOperands[$key][] = get_class($node);
                        }
                    }
                }
                elseif (count($this->currentFunctionName) > 0) {
                    $functionName = end($this->currentFunctionName);
                    $this->functionOperands[$functionName][] = $name;
                }
                break;
        }
    }
}
