<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators\Helpers;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Repository\RepositoryInterface;

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

    public function __construct(private RepositoryInterface $repository)
    {}

    public function beforeTraverse(): void
    {
        $packagesCollection = $this->repository->loadCollection(
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
            $this->repository->saveMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $packageData
            );

            if (! isset($this->abstractClasses[$packageName]) || ! isset($this->concreteClasses[$packageName])) {
                continue;
            }

            $packageMetrics = $this->repository->loadMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                [
                    'usesCount',
                    'usedByCount',
                ],
            );

            $instability = ($packageMetrics['usesCount']->getValue() + $packageMetrics['usedByCount']->getValue()) > 0 ? $packageMetrics['usesCount']->getValue() / ($packageMetrics['usesCount']->getValue() + $packageMetrics['usedByCount']->getValue()) : 0;
            $abstractness = (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName])) > 0 ?
                count($this->abstractClasses[$packageName]) / (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName]))
                : 0;
            $distanceFromMainline = $abstractness + $instability - 1;

            $newPackageMetrics = [
                'instability' => $instability,
                'abstractness' => $abstractness,
                'distanceFromMainline' => $distanceFromMainline,
            ];

            $this->repository->saveMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $newPackageMetrics
            );
        }
    }

    public function handlePackage(string $identifierString, $className): array
    {
        $classMetrics = $this->repository->loadMetricValues(
            null,
            $identifierString,
            ['package', 'realClass', 'abstract', 'interface']
        );

        $realClass = $classMetrics['realClass']->getValue();
        $abstract = $classMetrics['abstract']->getValue();
        $interface = $classMetrics['interface']->getValue();
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

        if (($realClass && $abstract || $interface) && ! in_array($className, $this->abstractClasses[$package])) {
            $this->abstractClasses[$package][] = $className;
        } elseif ($realClass && ! in_array($className, $this->abstractClasses[$package])) {
            $this->concreteClasses[$package][] = $className;
        }

        return [
            $this->abstractClasses,
            $this->concreteClasses,
        ];
    }

    public function handleDependency(string $dependency, string $identifierString, bool $isTrait): void
    {
        $usedByMetric = $this->repository->getMetricCollection(null, $identifierString);

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
