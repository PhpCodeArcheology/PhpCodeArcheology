<?php

/** @noinspection ALL */

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use function PhpCodeArch\getNodeName;

use PhpCodeArch\Graph\Graph;
use PhpCodeArch\Graph\Node as GraphNode;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class LcomVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private Graph $graph;

    private ?GraphNode $fromNode = null;

    /**
     * @var array<int, string>
     */
    private array $currentMethodName = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->graph = new Graph();

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\ClassMethod:
                $this->currentMethodName[] = (string) $node->name;

                $graphNodeName = $node->name.'()';
                if (!$this->graph->has($graphNodeName)) {
                    $this->graph->insert(new GraphNode($graphNodeName));
                }

                $this->fromNode = $this->graph->get($graphNodeName);
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->graph = new Graph();
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        $this->updateGraph($node);

        switch (true) {
            case $node instanceof Node\Stmt\ClassMethod:
                array_pop($this->currentMethodName);
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $lcom = 0;

                foreach ($this->graph->getNodes() as $graphNode) {
                    $lcom += $this->traverseNodes($graphNode);
                }

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => ClassName::ofNode($node)->__toString(),
                    ],
                    $lcom,
                    MetricKey::LCOM
                );

                $this->graph = new Graph();

                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        return null;
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

    public function updateGraph(Node $node): void
    {
        if (count($this->currentMethodName) > 0 && $node instanceof Node\Expr\MethodCall && (!$node->var instanceof Node\Expr\New_ && (property_exists($node->var, 'name') && null !== $node->var->name) && 'this' === getNodeName($node->var))) {
            $nodeName = getNodeName($node->name);
            if (null === $nodeName) {
                return;
            }
            $name = $nodeName.'()';
            if (!$this->graph->has($name)) {
                $this->graph->insert(new GraphNode($name));
            }
            $toNode = $this->graph->get($name);
            if (null !== $this->fromNode && null !== $toNode) {
                $this->graph->addEdge($this->fromNode, $toNode);
            }
        }

        if (count($this->currentMethodName) > 0
            && $node instanceof Node\Expr\PropertyFetch
            && (property_exists($node->var, 'name') && null !== $node->var->name)
            && 'this' === $node->var->name) {
            $name = getNodeName($node);
            if (null === $name) {
                return;
            }
            if (!$this->graph->has($name)) {
                $this->graph->insert(new GraphNode($name));
            }
            $toNode = $this->graph->get($name);
            if (null !== $this->fromNode && null !== $toNode) {
                $this->graph->addEdge($this->fromNode, $toNode);
            }
        }
    }
}
