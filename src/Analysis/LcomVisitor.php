<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Graph\Graph;
use Marcus\PhpLegacyAnalyzer\Graph\Node as GraphNode;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use PhpParser\Builder\Class_;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function Marcus\PhpLegacyAnalyzer\getNodeName;

class LcomVisitor implements NodeVisitor
{
    use VisitorTrait;

    private Graph $graph;

    private GraphNode $fromNode;

    private bool $inMethod = false;

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        $this->graph = new Graph();
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->inMethod = true;

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
        if ($this->inMethod && $node instanceof Node\Expr\MethodCall) {
            if (! $node->var instanceof Node\Expr\New_
                && isset($node->var->name)
                && getNodeName($node->var) === 'this') {

                $name = getNodeName($node->name) . '()';
                if (! $this->graph->has($name)) {
                    $this->graph->insert(new GraphNode($name));
                }
                $toNode = $this->graph->get($name);
                $this->graph->addEdge($this->fromNode, $toNode);
            }
        }

        if ($this->inMethod
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
            $this->inMethod = false;
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_) {

            $lcom = 0;
            foreach ($this->graph->getNodes() as $graphNode) {
                $lcom += $this->traverseNodes($graphNode);
            }

            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $classMetrics = $this->metrics->get($classId);
            $classMetrics->set('lcom', $lcom);
            $this->metrics->set($classId, $classMetrics);

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
