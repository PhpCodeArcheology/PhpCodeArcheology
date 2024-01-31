<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
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


    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
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
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);

                $functionMetricCollection = $this->repository->getMetricCollection(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ]
                );

                $this->repository->saveMetricValues(
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

                $methodMetricCollection = $this->repository->getMetricCollection(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ]
                );

                $this->repository->saveMetricValues(
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

                $classMetricCollection = $this->repository->getMetricCollection(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ]
                );

                $this->repository->saveMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $this->calculateIndex($classMetricCollection)
                );

                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileMetricCollection = $this->repository->getMetricCollection(
            MetricCollectionTypeEnum::FileCollection,
            [
                'path' => $this->path,
            ]
        );

        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            [
                'path' => $this->path,
            ],
            $this->calculateIndex($fileMetricCollection)
        );
    }

    private function calculateIndex(MetricsCollectionInterface $metric): array
    {
        $volume = $metric->get('volume')->getValue();
        $cc = $metric->get('cc')->getValue();

        $loc = $metric->get('loc')?->getValue() ?? 0;
        $cloc = $metric->get('cloc')?->getValue() ?? 0;
        $lloc = $metric->get('lloc')?->getValue() ?? 0;

        if ($volume == 0 || $lloc == 0) {
            return [
                'maintainabilityIndex' => 171,
                'maintainabilityIndexWithoutComments' => 50,
                'commentWeight' => 0,
            ];
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

        return [
            'maintainabilityIndex' => $maintainabilityIndex,
            'maintainabilityIndexWithoutComments' => $maintainabilityIndexWithoutComments,
            'commentWeight' => $commentWeight,
        ];
    }
}
