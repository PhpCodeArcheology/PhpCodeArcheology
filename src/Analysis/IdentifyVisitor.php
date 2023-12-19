<?php

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class IdentifyVisitor implements NodeVisitor
{
    private string $path;

    public function __construct(private Metrics $metrics)
    {
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

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
    public function enterNode(Node $node)
    {
        // TODO: Implement enterNode() method.
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        $metrics = null;

        if ($node instanceof Node\Stmt\Function_) {
            $metrics = new FunctionMetrics($this->path, $node->namespacedName);
        }
        elseif ($node instanceof Node\Stmt\Class_) {
            $metrics = new ClassMetrics($this->path, $node->namespacedName);

            $classMethods = [];
            foreach ($node->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassMethod) {
                    continue;
                }

                $method = new FunctionMetrics(path: '', name: $stmt->name);
                $classMethods[(string) $method->getIdentifier()] = $method;
            }

            $metrics->set('methods', $classMethods);
        }

        if ($metrics instanceof MetricsInterface) {
            $this->metrics->push($metrics);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // TODO: Implement afterTraverse() method.
    }
}