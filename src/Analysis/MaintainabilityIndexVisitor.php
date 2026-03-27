<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class MaintainabilityIndexVisitor implements NodeVisitor, VisitorInterface
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

    public function beforeTraverse(array $nodes): ?array
    {
        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $this->currentFunctionName[] = (string) $node->namespacedName;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->currentFunctionName[] = (string) $node->name;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->currentClassName[] = ClassName::ofNode($node)->__toString();
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);
                if (null === $functionName) {
                    break;
                }

                $functionMetricCollection = $this->metricsController->getMetricCollection(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ]
                );

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    $this->calculateIndex($functionMetricCollection)
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentFunctionName);
                if (false === $className || null === $methodName) {
                    break;
                }

                $methodMetricCollection = $this->metricsController->getMetricCollection(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ]
                );

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    $this->calculateIndex($methodMetricCollection)
                );

                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $classMetricCollection = $this->metricsController->getMetricCollection(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ]
                );

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->calculateIndex($classMetricCollection)
                );

                break;
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $fileMetricCollection = $this->metricsController->getMetricCollection(
            MetricCollectionTypeEnum::FileCollection,
            [
                'path' => $this->path,
            ]
        );

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            [
                'path' => $this->path,
            ],
            $this->calculateIndex($fileMetricCollection)
        );

        return null;
    }

    /**
     * @return array<string, float|int>
     */
    private function calculateIndex(MetricsCollectionInterface $metric): array
    {
        $volume = $metric->getFloat(MetricKey::VOLUME);
        $cc = $metric->getFloat(MetricKey::CC);

        $loc = $metric->getFloat(MetricKey::LOC);
        $cloc = $metric->getFloat(MetricKey::CLOC);
        $lloc = $metric->getFloat(MetricKey::LLOC);

        if (0 == $volume || 0 == $lloc) {
            return [
                MetricKey::MAINTAINABILITY_INDEX => 171,
                MetricKey::MAINTAINABILITY_INDEX_WITHOUT_COMMENTS => 50,
                MetricKey::COMMENT_WEIGHT => 0,
            ];
        }

        $maintainabilityIndexWithoutComments = max(171
            - 5.2 * log($volume)
            - 0.23 * $cc
            - 16.2 * log($lloc),
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

        return [
            MetricKey::MAINTAINABILITY_INDEX => $maintainabilityIndex,
            MetricKey::MAINTAINABILITY_INDEX_WITHOUT_COMMENTS => $maintainabilityIndexWithoutComments,
            MetricKey::COMMENT_WEIGHT => $commentWeight,
        ];
    }
}
