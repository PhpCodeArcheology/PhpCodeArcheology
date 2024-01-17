<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators\Helpers;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class PackageInstabilityAbstractnessCalculator
{
    private array $packages = [];

    private array $packagesMap = [
        'uses' => [],
        'usedBy' => [],
    ];

    private array $abstractClasses = [];
    private array $concreteClasses = [];
    private string $currentPackage;

    public function __construct(private MetricsController $metricsController)
    {}

    public function beforeTraverse(): void
    {
        $packagesCollection = $this->metricsController->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'packages'
        );

        $packages = array_flip($packagesCollection->getAsArray());

        $this->packages = array_map(function() {
            return [
                'usesCount' => 0,
                'usedByCount' => 0,
            ];
        }, $packages);
    }

    public function afterTraverse(): void
    {
        foreach ($this->packages as $packageName => $packageData) {
            if (! isset($this->abstractClasses[$packageName]) || ! isset($this->concreteClasses[$packageName])) {
                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::PackageCollection,
                    ['name' => $packageName],
                    [
                        'instability' => 0,
                        'abstractness' => 0,
                        'distanceFromMainline' => 0,
                    ],
                );
                continue;
            }

            $packageMetrics = $this->metricsController->getMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                [
                    'usesCount',
                    'usedByCount',
                ],
            );

            $instability = ($packageMetrics['usesCount'] + $packageMetrics['usedByCount']) > 0 ? $packageMetrics['usesCount'] / ($packageMetrics['usesCount'] + $packageMetrics['usedByCount']) : 0;
            $abstractness = (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName])) > 0 ?
                count($this->abstractClasses[$packageName]) / (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName]))
                : 0;
            $distanceFromMainline = $abstractness + $instability - 1;

            $newPackageMetrics = [
                'instability' => $instability,
                'abstractness' => $abstractness,
                'distanceFromMainline' => $distanceFromMainline,
            ];

            $this->metricsController->setMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $newPackageMetrics
            );
        }
    }

    public function handlePackage(string $identifierString, $className): array
    {
        $classMetrics = $this->metricsController->getMetricValuesByIdentifierString(
            $identifierString,
            ['package', 'realClass', 'abstract', 'interface']
        );

        $package = $classMetrics['package']->getValue();

        $this->currentPackage = $package;

        if (! isset($this->packagesMap['uses'][$package])) {
            $this->packagesMap['uses'][$package] = [];
        }
        if (! isset($this->packagesMap['usedBy'][$package])) {
            $this->packagesMap['usedBy'][$package] = [];
        }

        if (! isset($this->abstractClasses[$package])) {
            $this->abstractClasses[$package] = [];
        }

        if (! isset($this->concreteClasses[$package])) {
            $this->concreteClasses[$package] = [];
        }

        if (($classMetrics['realClass'] && $classMetrics['abstract'] || $classMetrics['interface']) && ! in_array($className, $this->abstractClasses[$package])) {
            $this->abstractClasses[$package][] = $className;
        } elseif ($classMetrics['realClass'] && ! in_array($className, $this->abstractClasses[$package])) {
            $this->concreteClasses[$package][] = $className;
        }

        return [
            $this->abstractClasses,
            $this->concreteClasses,
        ];
    }

    public function handleDependency(string $dependency, string $identifierString, bool $isTrait): void
    {
        $usedByMetric = $this->metricsController->getMetricCollectionByIdentifierString($identifierString);

        if ($this->currentPackage !== $usedByMetric->get('package')->getValue() && ! $isTrait) {
            if (! in_array($dependency, $this->packagesMap['uses'][$this->currentPackage])) {
                ++ $this->packages[$this->currentPackage]['usesCount'];
                $this->packagesMap['uses'][$this->currentPackage][] = $dependency;
            }
            if (! in_array($dependency, $this->packagesMap['usedBy'][$this->currentPackage])) {
                ++ $this->packages[$usedByMetric->get('package')->getValue()]['usedByCount'];
                $this->packagesMap['usedBy'][$this->currentPackage][] = $usedByMetric->getName();
            }
        }
    }
}
