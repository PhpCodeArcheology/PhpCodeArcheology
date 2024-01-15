<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators\Helpers;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;

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

    public function __construct(private MetricsContainer $metrics)
    {}

    public function beforeTraverse(): void
    {
        $packages = array_flip($this->metrics->get('packages') ?? []);

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
            $packageMetric = $this->metrics->get($packageName);

            if (! $packageMetric) {
                continue;
            }

            foreach ($packageData as $key => $value) {
                $packageMetric->set($key, $value);
            }

            if (! isset($this->abstractClasses[$packageName]) || ! isset($this->concreteClasses[$packageName])) {
                $packageMetric->set('instability', 0);
                $packageMetric->set('abstractness', 0);
                $packageMetric->set('distanceFromMainline', 0);

                $this->metrics->set($packageName, $packageMetric);
                continue;
            }


            $instability = ($packageMetric->get('usesCount') + $packageMetric->get('usedByCount')) > 0 ? $packageMetric->get('usesCount') / ($packageMetric->get('usesCount') + $packageMetric->get('usedByCount')) : 0;
            $abstractness = (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName])) > 0 ?
                count($this->abstractClasses[$packageName]) / (count($this->abstractClasses[$packageName]) + count($this->concreteClasses[$packageName]))
                : 0;
            $distanceFromMainline = $abstractness + $instability - 1;

            $packageMetric->set('instability', $instability);
            $packageMetric->set('abstractness', $abstractness);
            $packageMetric->set('distanceFromMainline', $distanceFromMainline);

            $this->metrics->set($packageName, $packageMetric);
        }
    }

    public function handlePackage(ClassMetricsCollection $metric): array
    {
        $package = $metric->get('package');
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

        if (($metric->get('realClass') && $metric->get('abstract') || $metric->get('interface')) && ! in_array($metric->getName(), $this->abstractClasses[$package])) {
            $this->abstractClasses[$package][] = $metric->getName();
        } elseif ($metric->get('realClass') && ! in_array($metric->getName(), $this->abstractClasses[$package])) {
            $this->concreteClasses[$package][] = $metric->getName();
        }

        return [
            $this->abstractClasses,
            $this->concreteClasses,
        ];
    }

    public function handleDependency(string $dependency, ClassMetricsCollection $usedByMetric, bool $isTrait): void
    {
        if ($this->currentPackage !== $usedByMetric->get('package') && ! $isTrait) {
            if (! in_array($dependency, $this->packagesMap['uses'][$this->currentPackage])) {
                ++ $this->packages[$this->currentPackage]['usesCount'];
                $this->packagesMap['uses'][$this->currentPackage][] = $dependency;
            }
            if (! in_array($dependency, $this->packagesMap['usedBy'][$this->currentPackage])) {
                ++ $this->packages[$usedByMetric->get('package')]['usedByCount'];
                $this->packagesMap['usedBy'][$this->currentPackage][] = $usedByMetric->getName();
            }
        }
    }
}
