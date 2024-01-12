<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class MaintainabilityIndexVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * @var MetricsInterface[]
     */
    private array $currentClass = [];

    /**
     * @var MetricsInterface[]
     */
    private array $currentFunction = [];


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

            $this->currentClass[] = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                $this->metrics,
                $node,
                $this->path
            );
        }
        elseif ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {

            if ($node instanceof Node\Stmt\Function_) {
                $this->currentFunction[] = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                    $this->metrics,
                    $node->namespacedName,
                    $this->path
                );
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
            || $node instanceof Node\Stmt\Enum_) {

            $currentMetric = array_pop($this->currentClass);
            $currentMetric = $this->calculateIndex($currentMetric);

            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $currentMetric = array_pop($this->currentFunction);

            $currentMetric = $this->calculateIndex($currentMetric);
            $this->metrics->set((string) $currentMetric->getIdentifier(), $currentMetric);
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $currentMetric = end($this->currentClass);

            $methods = $currentMetric->get('methods');

            $methodMetrics = FunctionMetricsFactory::createFromMethodsByNameAndClassMetrics(
                $methods,
                $node->name,
                $currentMetric
            );

            $methods[(string) $methodMetrics->getIdentifier()] = $this->calculateIndex($methodMetrics);

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
        $volume = $metric->get('volume')->getValue();
        $cc = $metric->get('cc')->getValue();

        $loc = $metric->get('loc')?->getValue() ?? 0;
        $cloc = $metric->get('cloc')?->getValue() ?? 0;
        $lloc = $metric->get('lloc')?->getValue() ?? 0;

        if ($volume == 0 || $lloc == 0) {
            $this->setMetricValues($metric, [
                'maintainabilityIndex' => 171,
                'maintainabilityIndexWithoutComments' => 50,
                'commentWeight' => 0,
            ]);

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

        $this->setMetricValues($metric, [
            'maintainabilityIndex' => $maintainabilityIndex,
            'maintainabilityIndexWithoutComments' => $maintainabilityIndexWithoutComments,
            'commentWeight' => $commentWeight,
        ]);

        return $metric;
    }
}
