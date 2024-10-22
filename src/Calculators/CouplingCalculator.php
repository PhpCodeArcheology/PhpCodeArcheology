<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\EnumNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class CouplingCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private ClassNameCollection $classes;

    private InterfaceNameCollection $interfaces;

    private ClassNameCollection $extends;

    private TraitNameCollection $traits;

    private EnumNameCollection $enums;

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    private array $abstractClasses = [];

    private array $concreteClasses = [];

    public function __construct(
        private readonly MetricsController $metricsController,
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
            $this->$collectionKey = $this->metricsController->getCollection(
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
        $name = $metrics->getName();

        $metricValues = null;

        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $metricValues = $this->handleFile($identifierString, $name);
                break;

            case $metrics instanceof ClassMetricsCollection:
                $metricValues = $this->handeClass($identifierString, $name);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $metricValues = $this->handleFunction($identifierString, $name);
                break;
        }

        if ($metricValues === null) {
            return;
        }

        $this->metricsController->setMetricValuesByIdentifierString(
            $identifierString,
            $metricValues
        );
    }

    public function afterTraverse(): void
    {
        $this->packageCalculator->afterTraverse();

        foreach ($this->classes as $classId => $className) {
            $metricValues = $this->metricsController->getMetricValuesByIdentifierString(
                $classId,
                [
                    'usesCount',
                    'usedByCount',
                    'usesForInstabilityCount',
                ]
            );

            $usesCount = $metricValues['usesCount']?->getValue() ?? 0;
            $usedByCount = $metricValues['usedByCount']?->getValue() ?? 0;
            $usesForInstabilityCount = $metricValues['usesForInstabilityCount']?->getValue() ?? 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $instability = ($usesForInstabilityCount + $usedByCount) > 0 ? $usesForInstabilityCount / ($usesForInstabilityCount + $usedByCount) : 0;

            $this->metricsController->setMetricValueByIdentifierString(
                $classId,
                'instability',
                $instability
            );

            $this->usesCount += $usesCount;
            $this->usedByCount += $usedByCount;
            $this->instability += $instability;
        }

        $avgUsesCount = count($this->classes) > 0 ? $this->usesCount / count($this->classes) : 0;
        $avgUsedByCount = count($this->classes) > 0 ? $this->usedByCount / count($this->classes) : 0;
        $avgInstability = count($this->classes) > 0 ? $this->instability / count($this->classes) : 0;

        $abstractClassesCount = array_reduce($this->abstractClasses, function($count, $packageClasses) {
            return $count + count($packageClasses);
        }, 0);

        $concreteClassesCount = array_reduce($this->concreteClasses, function($count, $packageClasses) {
            return $count + count($packageClasses);
        }, 0);

        $overallAbstractness = ($abstractClassesCount + $concreteClassesCount) > 0 ? $abstractClassesCount / ($abstractClassesCount + $concreteClassesCount) : 0;
        $overallDistanceFromMainline = $overallAbstractness + $avgInstability - 1;

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallAvgUsesCount' => $avgUsesCount,
                'overallAvgUsedByCount' => $avgUsedByCount,
                'overallAvgInstability' => $avgInstability,
                'overallAbstractness' => $overallAbstractness,
                'overallDistanceFromMainline' => $overallDistanceFromMainline,
            ]
        );
    }

    private function handeClass(string $identifierString, string $className): array
    {
        $metricValues = $this->metricsController->getMetricValuesByIdentifierString(
            $identifierString,
            [
                'uses',
                'usesCount',
                'usesInProject',
                'usesForInstability',
                'usedBy',
                'usedByCount',
                'usesInProjectCount',
                'usesForInstabilityCount',
            ]
        );

        foreach ($metricValues as $key => &$value) {
            if (str_ends_with($key, 'Count')) {
                $value = $value?->getValue() ?? 0;
                continue;
            }

            $value = $value?->getValue() ?? [];
        }

        [$this->abstractClasses, $this->concreteClasses] = $this->packageCalculator->handlePackage($identifierString, $className);

        $dependencyCollection = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'dependencies'
        );

        foreach ($dependencyCollection as $dependency) {
            if ($className === $dependency) {
                continue;
            }

            ++ $metricValues['usesCount'];
            $metricValues['uses'][] = $dependency;

            $checkArray = array_merge(
                $this->classes->getAsArray(),
                $this->interfaces->getAsArray(),
                $this->traits->getAsArray(),
                $this->enums->getAsArray()
            );

            $isTrait = true;

            if (in_array($dependency, $checkArray)) {
                // Counts only dependencies inside current project
                ++ $metricValues['usesInProjectCount'];
                $metricValues['usesInProject'][] = $dependency;

                if (! in_array($dependency, $this->traits->getAsArray())) {
                    $isTrait = false;
                    ++ $metricValues['usesForInstabilityCount'];
                }
            }

            $classIdentifierString = array_search($dependency, $checkArray);

            if (! $classIdentifierString) {
                continue;
            }

            $this->packageCalculator->handleDependency($dependency, $classIdentifierString, $isTrait);

            $usedByMetricValues = $this->metricsController->getMetricValuesByIdentifierString(
                $classIdentifierString,
                [
                    'usedBy',
                    'usedByCount',
                ],
            );

            $classUsedBy = $usedByMetricValues['usedBy']?->getValue() ?? [];
            $classUsedByCount = $usedByMetricValues['usedByCount']?->getValue() ?? 0;

            $classUsedBy[] = $className;
            ++ $classUsedByCount;

            $this->metricsController->setMetricValuesByIdentifierString(
                $classIdentifierString,
                [
                    'usedBy' => $classUsedBy,
                    'usedByCount' => $classUsedByCount,
                ]
            );
        }

        return $metricValues;
    }

    private function handleFile(string $identifierString, string $name): ?array
    {
        return $this->handleMetric($identifierString, 'usedFromOutside', 'usedFromOutsideCount', $name);
    }

    private function handleFunction(string $identifierString, string $name): ?array
    {
        return $this->handleMetric($identifierString, 'usedByFunction', 'usedByFunctionCount', $name);
    }

    private function handleMetric(string $identifierString, string $usedByKey, string $usedByCountKey, string $name): ?array
    {
        $usedBy = $this->metricsController->getMetricValueByIdentifierString(
            $identifierString,
            $usedByKey
        );

        $usedByCount = $this->metricsController->getMetricValueByIdentifierString(
            $identifierString,
            $usedByCountKey
        );

        $dependencyCollection = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'dependencies'
        ) ?? [];

        $classList = $this->classes->getAsArray();

        foreach ($dependencyCollection as $dependency) {
            $classKey = array_search($dependency, $classList);
            if (!$classKey) {
                continue;
            }

            $usedBy[] = $name;
            ++ $usedByCount;
        }

        return [
            $usedByKey => $usedBy,
            $usedByCountKey => $usedByCount,
        ];
    }
}
