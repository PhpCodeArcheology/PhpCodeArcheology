<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class InheritanceDepthCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    /** @var array<string, string> classIdentifier → parentClassIdentifier */
    private array $parentMap = [];

    /** @var array<string, string> className → classIdentifier */
    private array $nameToId = [];

    /** @var array<string, int> classIdentifier → DIT */
    private array $ditCache = [];

    /** @var array<string, int> classIdentifier → NOC (children count) */
    private array $nocCount = [];

    public function beforeTraverse(): void
    {
        $this->parentMap = [];
        $this->nameToId = [];
        $this->ditCache = [];
        $this->nocCount = [];

        // Build nameToId map from project classes
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
                $this->nameToId[$name] = $id;
                $this->nocCount[$id] = 0;
            }
        }
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();

        // Get extends collection
        $extends = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'extends'
        );

        if ($extends === null) {
            return;
        }

        $extendsList = $extends->getAsArray();
        if (empty($extendsList)) {
            return;
        }

        // PHP only allows single inheritance, take first
        $parentName = reset($extendsList);
        if ($parentName === null || $parentName === '') {
            return;
        }
        $parentId = $this->nameToId[$parentName] ?? null;

        if ($parentId !== null) {
            $this->parentMap[$identifierString] = $parentId;
            $this->nocCount[$parentId] = ($this->nocCount[$parentId] ?? 0) + 1;
        } else {
            // External parent class: DIT starts at 1
            $this->parentMap[$identifierString] = '__external__';
        }
    }

    public function afterTraverse(): void
    {
        // Compute DIT for each class
        foreach ($this->nameToId as $name => $id) {
            $dit = $this->computeDit($id);

            $this->metricsController->setMetricValuesByIdentifierString(
                $id,
                [
                    'dit' => $dit,
                    'noc' => $this->nocCount[$id] ?? 0,
                ]
            );
        }
    }

    private function computeDit(string $id): int
    {
        if (isset($this->ditCache[$id])) {
            return $this->ditCache[$id];
        }

        if (!isset($this->parentMap[$id])) {
            $this->ditCache[$id] = 0;
            return 0;
        }

        $parentId = $this->parentMap[$id];

        if ($parentId === '__external__') {
            $this->ditCache[$id] = 1;
            return 1;
        }

        // Guard against cycles in inheritance
        $this->ditCache[$id] = 0; // Sentinel to break cycles
        $dit = 1 + $this->computeDit($parentId);
        $this->ditCache[$id] = $dit;

        return $dit;
    }
}
