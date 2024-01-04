<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class HalsteadMetricsVisitor extends NodeVisitorAbstract
{
    use VisitorTrait;

    private array $operators = [];

    private array $operands = [];

    private array $classOperators = [];

    private array $classOperands = [];

    private array $functionOperators = [];

    private array $functionOperands = [];

    private bool $insideClass = false;

    private bool $insideFunction = false;

    /**
     * @var MetricsInterface[]
     */
    private array $currentMetric = [];

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
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $this->insideClass = true;
            $this->classOperators = [];
            $this->classOperands = [];

            $className = (string) ClassName::ofNode($node);
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, $this->path);
            $this->currentMetric[] = $this->metrics->get($classId);
        }
        elseif ($node instanceof Node\Stmt\Function_
            || $node instanceof  Node\Stmt\ClassMethod) {
            $this->insideFunction = true;
            $this->functionOperators = [];
            $this->functionOperands = [];

            if ($node instanceof Node\Stmt\Function_) {
                $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
                $this->currentMetric[] = $this->metrics->get($functionId);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Expr\BinaryOp
            || $node instanceof Node\Expr\AssignOp
            || $node instanceof Node\Stmt\If_
            || $node instanceof Node\Stmt\ElseIf_
            || $node instanceof Node\Stmt\Else_
            || $node instanceof Node\Stmt\For_
            || $node instanceof Node\Stmt\Foreach_
            || $node instanceof Node\Stmt\Switch_
            || $node instanceof Node\Expr\Match_
            || $node instanceof Node\Stmt\Catch_
            || $node instanceof Node\Stmt\Return_
            || $node instanceof Node\Stmt\While_
            || $node instanceof Node\Stmt\Do_
            || $node instanceof Node\Expr\Assign
            || $node instanceof Node\Expr\Ternary
            || $node instanceof Node\Expr\BooleanNot
            || $node instanceof Node\Expr\BitwiseNot
            || $node instanceof Node\Expr\FuncCall
            || $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\New_
            || $node instanceof Node\Expr\Instanceof_
            || $node instanceof Node\Expr\UnaryMinus
            || $node instanceof Node\Expr\UnaryPlus
            || $node instanceof Node\Expr\PreDec
            || $node instanceof Node\Expr\PreInc
            || $node instanceof Node\Expr\PostDec
            || $node instanceof Node\Expr\PostInc
            || $node instanceof Node\Stmt\TryCatch
            || $node instanceof Node\Stmt\Throw_
        ) {
            $this->operators[] = get_class($node);

            if ($this->insideClass) {
                $this->classOperators[] = get_class($node);

                if ($this->insideFunction) {
                    $this->functionOperators[] = get_class($node);
                }
            }
            elseif ($this->insideFunction) {
                $this->functionOperators[] = get_class($node);
            }
        }

        if ($node instanceof Node\Expr\Cast
            || $node instanceof Node\Expr\Variable
            || $node instanceof Node\Param
            || $node instanceof Node\Scalar) {

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

            if ($this->insideClass) {
                $this->classOperands[] = $name;

                if ($this->insideFunction) {
                    $this->functionOperands[] = $name;
                }
            }
            elseif ($this->insideFunction) {
                $this->functionOperands[] = $name;
            }
        }

        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $currentMetric = array_pop($this->currentMetric);

            $halstead = $this->calculateMetrics($this->classOperators, $this->classOperands);
            $currentMetric = $this->saveToMetric($currentMetric, $halstead);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);

        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $currentMetric = array_pop($this->currentMetric);

            $halstead = $this->calculateMetrics($this->functionOperators, $this->functionOperands);
            $currentMetric = $this->saveToMetric($currentMetric, $halstead);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $currentMetric = end($this->currentMetric);

            $halstead = $this->calculateMetrics($this->functionOperators, $this->functionOperands);
            $methods = $currentMetric->get('methods');

            $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, (string) $currentMetric->getIdentifier());
            $methodMetrics = $methods[$methodId];

            $methods[$methodId] = $this->saveToMetric($methodMetrics, $halstead);
            $currentMetric->set('methods', $methods);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        // Calculate file metrics
        $halstead = $this->calculateMetrics($this->operators, $this->operands);

        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);

        $fileMetrics = $this->saveToMetric($fileMetrics, $halstead);

        $this->metrics->set($fileId, $fileMetrics);
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

    private function saveToMetric(MetricsInterface $metrics, array $data): MetricsInterface
    {
        foreach ($data as $key => $value) {
            $metrics->set($key, $value);
        }

        return $metrics;
    }

    private function afterSetPath(): void
    {
        $this->operators = [];
        $this->operands = [];
    }
}
