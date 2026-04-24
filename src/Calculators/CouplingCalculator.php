<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class CouplingCalculator implements CalculatorInterface
{
    /** @var array<string, CollectionInterface|null> */
    private array $collectionMap = [];

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    /** @var array<string, string[]> */
    private array $abstractClasses = [];

    /** @var array<string, string[]> */
    private array $concreteClasses = [];

    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsWriterInterface $writer,
        private readonly PackageInstabilityAbstractnessCalculator $packageCalculator)
    {
    }

    public function beforeTraverse(): void
    {
        $collections = [
            'classes',
            'interfaces',
            'traits',
            'enums',
        ];

        foreach ($collections as $collectionKey) {
            $this->collectionMap[$collectionKey] = $this->reader->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collectionKey
            );
        }

        $this->packageCalculator->beforeTraverse();
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        $identifierString = (string) $metrics->getIdentifier();

        $metricValues = null;

        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $name = $metrics->getName();
                $metricValues = $this->handleFile($identifierString, $name);
                break;

            case $metrics instanceof ClassMetricsCollection:
                $name = $metrics->getName();
                $metricValues = $this->handleClass($identifierString, $name);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $name = $metrics->getName();
                $metricValues = $this->handleFunction($identifierString, $name);
                break;
        }

        if (null === $metricValues) {
            return;
        }

        $this->writer->setMetricValuesByIdentifierString(
            $identifierString,
            $metricValues
        );
    }

    public function afterTraverse(): void
    {
        $this->packageCalculator->afterTraverse();

        $classesCollection = $this->collectionMap['classes'];
        if (!$classesCollection instanceof CollectionInterface) {
            return;
        }

        foreach ($classesCollection->getAsArray() as $classId => $className) {
            if (!is_string($classId)) {
                continue;
            }

            $metricValues = $this->reader->getMetricValuesByIdentifierString(
                $classId,
                [
                    MetricKey::USES_COUNT,
                    MetricKey::USED_BY_COUNT,
                    MetricKey::USES_FOR_INSTABILITY_COUNT,
                ]
            );

            $usesCount = $metricValues[MetricKey::USES_COUNT]?->asInt() ?? 0;
            $usedByCount = $metricValues[MetricKey::USED_BY_COUNT]?->asInt() ?? 0;
            $usesForInstabilityCount = $metricValues[MetricKey::USES_FOR_INSTABILITY_COUNT]?->asInt() ?? 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $instability = ($usesForInstabilityCount + $usedByCount) > 0 ? $usesForInstabilityCount / ($usesForInstabilityCount + $usedByCount) : 0;

            $this->writer->setMetricValueByIdentifierString(
                $classId,
                MetricKey::INSTABILITY,
                $instability
            );

            $this->usesCount += $usesCount;
            $this->usedByCount += $usedByCount;
            $this->instability += $instability;
        }

        $classCount = $classesCollection->count();
        $avgUsesCount = $classCount > 0 ? $this->usesCount / $classCount : 0;
        $avgUsedByCount = $classCount > 0 ? $this->usedByCount / $classCount : 0;
        $avgInstability = $classCount > 0 ? $this->instability / $classCount : 0;

        $abstractClassesCount = array_reduce($this->abstractClasses, fn (int|float $count, array $packageClasses) => $count + count($packageClasses), 0);

        $concreteClassesCount = array_reduce($this->concreteClasses, fn (int|float $count, array $packageClasses) => $count + count($packageClasses), 0);

        $overallAbstractness = ($abstractClassesCount + $concreteClassesCount) > 0 ? $abstractClassesCount / ($abstractClassesCount + $concreteClassesCount) : 0;
        $overallDistanceFromMainline = abs($overallAbstractness + $avgInstability - 1);

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_AVG_USES_COUNT => $avgUsesCount,
                MetricKey::OVERALL_AVG_USED_BY_COUNT => $avgUsedByCount,
                MetricKey::OVERALL_AVG_INSTABILITY => $avgInstability,
                MetricKey::OVERALL_ABSTRACTNESS => $overallAbstractness,
                MetricKey::OVERALL_DISTANCE_FROM_MAINLINE => $overallDistanceFromMainline,
            ]
        );
    }

    /** @return array<string, mixed> */
    private function handleClass(string $identifierString, string $className): array
    {
        $metricValues = $this->reader->getMetricValuesByIdentifierString(
            $identifierString,
            [
                MetricKey::USES,
                MetricKey::USES_COUNT,
                MetricKey::USES_IN_PROJECT,
                MetricKey::USES_FOR_INSTABILITY,
                MetricKey::USED_BY,
                MetricKey::USED_BY_COUNT,
                MetricKey::USES_IN_PROJECT_COUNT,
                MetricKey::USES_FOR_INSTABILITY_COUNT,
            ]
        );

        $usesCount = $metricValues[MetricKey::USES_COUNT]?->asInt() ?? 0;
        $usesInProjectCount = $metricValues[MetricKey::USES_IN_PROJECT_COUNT]?->asInt() ?? 0;
        $usesForInstabilityCount = $metricValues[MetricKey::USES_FOR_INSTABILITY_COUNT]?->asInt() ?? 0;
        $usedByCount = $metricValues[MetricKey::USED_BY_COUNT]?->asInt() ?? 0;
        $uses = $metricValues[MetricKey::USES]?->asArray() ?? [];
        $usesInProject = $metricValues[MetricKey::USES_IN_PROJECT]?->asArray() ?? [];
        $usesForInstability = $metricValues[MetricKey::USES_FOR_INSTABILITY]?->asArray() ?? [];
        $usedBy = $metricValues[MetricKey::USED_BY]?->asArray() ?? [];

        [$this->abstractClasses, $this->concreteClasses] = $this->packageCalculator->handlePackage($identifierString, $className);

        $dependencyCollection = $this->reader->getCollectionByIdentifierString(
            $identifierString,
            'dependencies'
        );

        if ($dependencyCollection instanceof CollectionInterface) {
            foreach ($dependencyCollection as $dependency) {
                if (!is_string($dependency) || $className === $dependency) {
                    continue;
                }

                ++$usesCount;
                $uses[] = $dependency;

                $classesArray = $this->collectionMap['classes'] instanceof CollectionInterface
                    ? $this->collectionMap['classes']->getAsArray()
                    : [];
                $interfacesArray = $this->collectionMap['interfaces'] instanceof CollectionInterface
                    ? $this->collectionMap['interfaces']->getAsArray()
                    : [];
                $traitsArray = $this->collectionMap['traits'] instanceof CollectionInterface
                    ? $this->collectionMap['traits']->getAsArray()
                    : [];
                $enumsArray = $this->collectionMap['enums'] instanceof CollectionInterface
                    ? $this->collectionMap['enums']->getAsArray()
                    : [];

                $checkArray = array_merge($classesArray, $interfacesArray, $traitsArray, $enumsArray);

                $isTrait = true;

                if (in_array($dependency, $checkArray, true)) {
                    // Counts only dependencies inside current project
                    ++$usesInProjectCount;
                    $usesInProject[] = $dependency;

                    if (!in_array($dependency, $traitsArray, true)) {
                        $isTrait = false;
                        ++$usesForInstabilityCount;
                        $usesForInstability[] = $dependency;
                    }
                }

                $classIdentifierString = array_search($dependency, $checkArray, true);

                if (!is_string($classIdentifierString)) {
                    continue;
                }

                $this->packageCalculator->handleDependency($dependency, $classIdentifierString, $isTrait);

                $usedByMetricValues = $this->reader->getMetricValuesByIdentifierString(
                    $classIdentifierString,
                    [
                        MetricKey::USED_BY,
                        MetricKey::USED_BY_COUNT,
                    ],
                );

                $classUsedBy = $usedByMetricValues[MetricKey::USED_BY]?->asArray() ?? [];
                $classUsedByCount = $usedByMetricValues[MetricKey::USED_BY_COUNT]?->asInt() ?? 0;

                $classUsedBy[] = $className;
                ++$classUsedByCount;

                $this->writer->setMetricValuesByIdentifierString(
                    $classIdentifierString,
                    [
                        MetricKey::USED_BY => $classUsedBy,
                        MetricKey::USED_BY_COUNT => $classUsedByCount,
                    ]
                );
            }
        }

        return [
            MetricKey::USES => $uses,
            MetricKey::USES_COUNT => $usesCount,
            MetricKey::USES_IN_PROJECT => $usesInProject,
            MetricKey::USES_FOR_INSTABILITY => $usesForInstability,
            MetricKey::USED_BY => $usedBy,
            MetricKey::USED_BY_COUNT => $usedByCount,
            MetricKey::USES_IN_PROJECT_COUNT => $usesInProjectCount,
            MetricKey::USES_FOR_INSTABILITY_COUNT => $usesForInstabilityCount,
        ];
    }

    /** @return array<string, mixed> */
    private function handleFile(string $identifierString, string $name): array
    {
        return $this->handleMetric($identifierString, MetricKey::USED_FROM_OUTSIDE, MetricKey::USED_FROM_OUTSIDE_COUNT, $name);
    }

    /** @return array<string, mixed> */
    private function handleFunction(string $identifierString, string $name): array
    {
        return $this->handleMetric($identifierString, MetricKey::USED_BY_FUNCTION, MetricKey::USED_BY_FUNCTION_COUNT, $name);
    }

    /** @return array<string, mixed> */
    private function handleMetric(string $identifierString, string $usedByKey, string $usedByCountKey, string $name): array
    {
        $usedBy = $this->reader->getMetricValueByIdentifierString(
            $identifierString,
            $usedByKey
        )?->asArray() ?? [];

        $usedByCount = $this->reader->getMetricValueByIdentifierString(
            $identifierString,
            $usedByCountKey
        )?->asInt() ?? 0;

        $dependencyCollection = $this->reader->getCollectionByIdentifierString(
            $identifierString,
            'dependencies'
        );

        $classList = $this->collectionMap['classes'] instanceof CollectionInterface
            ? $this->collectionMap['classes']->getAsArray()
            : [];

        if ($dependencyCollection instanceof CollectionInterface) {
            foreach ($dependencyCollection as $dependency) {
                $classKey = array_search($dependency, $classList, true);
                if (!$classKey) {
                    continue;
                }

                $usedBy[] = $name;
                ++$usedByCount;
            }
        }

        return [
            $usedByKey => $usedBy,
            $usedByCountKey => $usedByCount,
        ];
    }
}
