<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
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

class CyclomaticComplexityVisitor implements NodeVisitor
{
    use VisitorTrait;

    private int $fileCc = 1;

    private ?ClassMetrics $currentClass = null;

    private int $currentClassCc = 1;

    private ?FunctionMetrics $currentFunction = null;

    private int $currentFunctionCc = 1;

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->getFileMetrics();
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_) {
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $this->currentClass = $this->metrics->get($classId);
            $this->currentClassCc = 1;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $methods = $this->currentClass->get('methods');

            $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, '');
            $this->currentFunction = $methods[$functionId];
            $this->currentFunctionCc = 1;
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $this->currentFunction = $this->metrics->get($functionId);
            $this->currentFunctionCc = 1;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        $increase = $this->getIncreaseForNode($node);

        $this->fileCc += $increase;

        if ($this->currentClass) {
            $this->currentClassCc += $increase;

            if ($this->currentFunction) {
                $this->currentFunctionCc += $increase;
            }
        }
        elseif ($this->currentFunction) {
            $this->currentFunctionCc += $increase;
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass->set('cc', $this->currentClassCc);
            $this->metrics->set((string) $this->currentClass->getIdentifier(), $this->currentClass);

            $this->currentClass = null;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $methods = $this->currentClass->get('methods');
            $this->currentFunction->set('cc', $this->currentFunctionCc);
            $methods[(string) $this->currentFunction->getIdentifier()] = $this->currentFunction;
            $this->metrics->set((string) $this->currentClass->getIdentifier(), $this->currentClass);

            $this->currentFunction = null;
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->currentFunction->set('cc', $this->currentFunctionCc);
            $this->metrics->set((string) $this->currentFunction->getIdentifier(), $this->currentFunction);

            $this->currentFunction = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $this->fileMetrics->set('cc', $this->fileCc);
        $this->metrics->set((string) $this->fileMetrics->getIdentifier(), $this->fileMetrics);
    }

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