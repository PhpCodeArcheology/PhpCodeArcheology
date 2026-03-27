<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;

class PackageCohesionCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    /** @var array<string, string[]> packageId → [classId, ...] */
    private array $packageClasses = [];

    /** @var array<string, string> className → classId */
    private array $nameToId = [];

    public function beforeTraverse(): void
    {
        $this->packageClasses = [];
        $this->nameToId = [];

        $collections = ['classes', 'interfaces', 'traits', 'enums'];
        foreach ($collections as $collectionKey) {
            $items = $this->metricsController->getCollection(
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
                $this->nameToId[$name] = $id;
            }
        }
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof PackageMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();

        // Get classes in this package
        $classCollection = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'classes'
        );

        if (!$classCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            return;
        }

        $classIds = [];
        foreach ($classCollection->getAsArray() as $className) {
            if (!is_string($className)) {
                continue;
            }
            // Resolve className to actual metric identifier via nameToId map
            $id = $this->nameToId[$className] ?? null;
            if (null !== $id) {
                $classIds[$className] = $id;
            }
        }

        $this->packageClasses[$identifierString] = $classIds;
    }

    public function afterTraverse(): void
    {
        foreach ($this->packageClasses as $packageId => $classIds) {
            $n = count($classIds);
            if ($n <= 1) {
                $this->metricsController->setMetricValueByIdentifierString(
                    $packageId,
                    MetricKey::PACKAGE_COHESION,
                    $n > 0 ? 1.0 : 0.0
                );
                continue;
            }

            // Count internal relationships: how many classes in this package use other classes in the same package
            $internalRelations = 0;
            $classNamesInPackage = array_keys($classIds);

            foreach ($classIds as $className => $classId) {
                $usedClasses = $this->metricsController->getCollectionByIdentifierString($classId, 'usedClasses');
                if (!$usedClasses instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                    continue;
                }

                foreach ($usedClasses->getAsArray() as $usedClassName) {
                    if (in_array($usedClassName, $classNamesInPackage) && $usedClassName !== $className) {
                        ++$internalRelations;
                    }
                }
            }

            // H = (R + 1) / N
            $cohesion = min(1.0, round(($internalRelations + 1) / $n, 2));

            $this->metricsController->setMetricValueByIdentifierString(
                $packageId,
                'packageCohesion',
                $cohesion
            );
        }
    }
}
