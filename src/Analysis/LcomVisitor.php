<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Graph\Graph;
use PhpCodeArch\Graph\Node as GraphNode;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function PhpCodeArch\getNodeName;

class LcomVisitor implements NodeVisitor
{
    use VisitorTrait;

    private Graph $graph;

    private GraphNode $fromNode;

    /**
     * @var string[]
     */
    private array $currentMethodName = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->graph = new Graph();
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->graph = new Graph();
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->currentMethodName[] = (string) $node->name;

            $graphNodeName = $node->name . '()';
            if (! $this->graph->has($graphNodeName)) {
                $this->graph->insert(new GraphNode($graphNodeName));
            }

            $this->fromNode = $this->graph->get($graphNodeName);
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        if (count($this->currentMethodName) > 0 && $node instanceof Node\Expr\MethodCall) {
            if (! $node->var instanceof Node\Expr\New_
                && isset($node->var->name)
                && getNodeName($node->var) === 'this') {

                $nodeName = getNodeName($node->name);
                $name = $nodeName . '()';
                if (! $this->graph->has($name)) {
                    $this->graph->insert(new GraphNode($name));
                }
                $toNode = $this->graph->get($name);
                $this->graph->addEdge($this->fromNode, $toNode);
            }
        }

        if (count($this->currentMethodName) > 0
            && $node instanceof Node\Expr\PropertyFetch
            && isset ($node->var->name)
            && $node->var->name === 'this') {

            $name = getNodeName($node);
            if (! $this->graph->has($name)) {
                $this->graph->insert(new GraphNode($name));
            }
            $toNode = $this->graph->get($name);
            $this->graph->addEdge($this->fromNode, $toNode);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            array_pop($this->currentMethodName);
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $lcom = 0;
            foreach ($this->graph->getNodes() as $graphNode) {
                $lcom += $this->traverseNodes($graphNode);
            }

            $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                $this->metrics,
                $node,
                $this->path
            );

            $classMetrics->set('lcom', $lcom);
            $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);

            $this->graph = new Graph();
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // TODO: Implement afterTraverse() method.
    }

    private function traverseNodes(GraphNode $node): int
    {
        if ($node->isVisited()) {
            return 0;
        }

        $node->visit();

        foreach ($node->getAdjacents() as $adjacent) {
            $this->traverseNodes($adjacent);
        }

        return 1;
    }
}
