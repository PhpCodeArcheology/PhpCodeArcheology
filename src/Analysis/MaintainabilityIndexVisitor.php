<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class MaintainabilityIndexVisitor implements NodeVisitor
{
    use VisitorTrait;

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

            $className = (string) ClassName::ofNode($node);
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, $this->path);
            $this->currentMetric[] = $this->metrics->get($classId);
        }
        elseif ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {

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
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Enum_) {
            $currentMetric = array_pop($this->currentMetric);

            $currentMetric = $this->calculateIndex($currentMetric);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $currentMetric = end($this->currentMetric);

            $methods = $currentMetric->get('methods');

            $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, (string) $currentMetric->getIdentifier());
            $methodMetrics = $methods[$methodId];
            $methods[$methodId] = $this->calculateIndex($methodMetrics);

            $currentMetric->set('methods', $methods);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);

        $this->metrics->set($fileId, $this->calculateIndex($fileMetrics));
    }

    private function calculateIndex(MetricsInterface $metric): MetricsInterface
    {
        $volume = $metric->get('volume');
        $cc = $metric->get('cc');
        $loc = $metric->get('loc');
        $cloc = $metric->get('cloc');
        $lloc = $metric->get('lloc');

        if ($volume == 0 || $lloc == 0) {
            $metric->set('maintainabilityIndex', 50);
            $metric->set('maintainabilityIndexWithoutComments', 50);
            $metric->set('commentWeight', 0);

            return $metric;
        }

        $maintainabilityIndexWithoutComments = max((171
            - 5.2 * log($volume)
            - 0.23 * $cc
            - 16.2 * log($lloc)),
            0
        );

        if (is_infinite($maintainabilityIndexWithoutComments)) {
            $maintainabilityIndexWithoutComments = 171;
        }

        $commentWeight = 0;
        if ($loc > 0) {
            $commentWeight = $cloc / $loc;
            $commentWeight = 50 * sin(sqrt(2.4 * $commentWeight));
        }

        $maintainabilityIndex = $maintainabilityIndexWithoutComments + $commentWeight;

        $metric->set('maintainabilityIndex', $maintainabilityIndex);
        $metric->set('maintainabilityIndexWithoutComments', $maintainabilityIndexWithoutComments);
        $metric->set('commentWeight', $commentWeight);

        return $metric;
    }
}
