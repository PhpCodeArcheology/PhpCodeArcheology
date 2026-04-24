<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Calculators\Helpers\TarjanSccAlgorithm;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class DependencyCycleCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

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
            $items = $this->reader->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collectionKey
            );

            if (!$items instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                continue;
            }

            foreach ($items->getAsArray() as $id => $name) {
                if (!is_string($id) || !is_string($name)) {
                    continue;
                }
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
        $usedClasses = $this->reader->getCollectionByIdentifierString(
            $identifierString,
            'usedClasses'
        );

        if (!$usedClasses instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            return;
        }

        foreach ($usedClasses->getAsArray() as $usedClassName) {
            if (!is_string($usedClassName)) {
                continue;
            }
            $usedId = $this->nameToId[$usedClassName] ?? null;
            if (null !== $usedId && $usedId !== $identifierString) {
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
                fn (string $id) => $this->idToName[$id] ?? $id,
                $cycle
            );
            $cycleLength = count($cycle);

            foreach ($cycle as $classId) {
                ++$classesInCycles;
                $this->writer->setMetricValuesByIdentifierString(
                    $classId,
                    [
                        MetricKey::IN_DEPENDENCY_CYCLE => true,
                        MetricKey::DEPENDENCY_CYCLE_LENGTH => $cycleLength,
                        MetricKey::DEPENDENCY_CYCLE_CLASSES => $cycleNames,
                    ]
                );
            }
        }

        // Set default values for classes NOT in cycles
        foreach (array_keys($this->idToName) as $id) {
            $existing = $this->reader->getMetricValueByIdentifierString($id, MetricKey::IN_DEPENDENCY_CYCLE);
            if (!$existing instanceof \PhpCodeArch\Metrics\Model\MetricValue || null === $existing->getValue()) {
                $this->writer->setMetricValuesByIdentifierString(
                    $id,
                    [
                        MetricKey::IN_DEPENDENCY_CYCLE => false,
                        MetricKey::DEPENDENCY_CYCLE_LENGTH => 0,
                        MetricKey::DEPENDENCY_CYCLE_CLASSES => [],
                    ]
                );
            }
        }

        // Project-level metrics
        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_DEPENDENCY_CYCLES => $totalCycles,
                MetricKey::OVERALL_CLASSES_IN_CYCLES => $classesInCycles,
            ]
        );
    }
}
