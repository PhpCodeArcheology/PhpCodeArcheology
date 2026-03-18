<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Calculators\Helpers\TarjanSccAlgorithm;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class DependencyCycleCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    /** @var array<string, string> classIdentifier → className */
    private array $idToName = [];

    /** @var array<string, string> className → classIdentifier */
    private array $nameToId = [];

    /** @var array<string, string[]> classIdentifier → [dependencyClassIdentifier, ...] */
    private array $adjacencyList = [];

    public function beforeTraverse(): void
    {
        $this->idToName = [];
        $this->nameToId = [];
        $this->adjacencyList = [];

        $collections = ['classes', 'interfaces', 'traits', 'enums'];
        foreach ($collections as $collectionKey) {
            $items = $this->metricsController->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collectionKey
            );

            if ($items === null) {
                continue;
            }

            foreach ($items->getAsArray() as $id => $name) {
                $this->idToName[$id] = $name;
                $this->nameToId[$name] = $id;
                $this->adjacencyList[$id] = [];
            }
        }
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();

        // Get usedClasses collection (dependencies from DependencyVisitor)
        $usedClasses = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'usedClasses'
        );

        if ($usedClasses === null) {
            return;
        }

        foreach ($usedClasses->getAsArray() as $usedClassName) {
            $usedId = $this->nameToId[$usedClassName] ?? null;
            if ($usedId !== null && $usedId !== $identifierString) {
                $this->adjacencyList[$identifierString][] = $usedId;
            }
        }
    }

    public function afterTraverse(): void
    {
        $tarjan = new TarjanSccAlgorithm();
        $cycles = $tarjan->findCycles($this->adjacencyList);

        $totalCycles = count($cycles);
        $classesInCycles = 0;

        foreach ($cycles as $cycle) {
            $cycleNames = array_map(
                fn(string $id) => $this->idToName[$id] ?? $id,
                $cycle
            );
            $cycleLength = count($cycle);

            foreach ($cycle as $classId) {
                $classesInCycles++;
                $this->metricsController->setMetricValuesByIdentifierString(
                    $classId,
                    [
                        'inDependencyCycle' => true,
                        'dependencyCycleLength' => $cycleLength,
                        'dependencyCycleClasses' => $cycleNames,
                    ]
                );
            }
        }

        // Set default values for classes NOT in cycles
        foreach ($this->idToName as $id => $name) {
            $existing = $this->metricsController->getMetricValueByIdentifierString($id, 'inDependencyCycle');
            if ($existing === null || $existing->getValue() === null) {
                $this->metricsController->setMetricValuesByIdentifierString(
                    $id,
                    [
                        'inDependencyCycle' => false,
                        'dependencyCycleLength' => 0,
                        'dependencyCycleClasses' => [],
                    ]
                );
            }
        }

        // Project-level metrics
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallDependencyCycles' => $totalCycles,
                'overallClassesInCycles' => $classesInCycles,
            ]
        );
    }
}
