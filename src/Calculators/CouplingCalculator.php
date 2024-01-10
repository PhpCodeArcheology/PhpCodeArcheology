<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\MetricsInterface;

class CouplingCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private array $classes = [];

    private array $interfaces = [];

    private array $extends = [];

    private array $traits = [];

    private array $packages = [];

    private array $packagesMap = [
        'uses' => [],
        'usedBy' => [],
    ];

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    private array $abstractClasses = [];

    private array $concreteClasses = [];

    public function beforeTraverse(): void
    {
        $this->classes = $this->metrics->get('classes') ?? [];
        $this->interfaces = $this->metrics->get('interfaces') ?? [];
        $this->traits = $this->metrics->get('traits') ?? [];

        $packages = $this->metrics->get('packages') ?? [];

        $packages = array_flip($packages);

        $this->packages = array_map(function() {
            return [
                'usesCount' => 0,
                'usedByCount' => 0,
            ];
        }, $packages);
    }

    public function calculate(MetricsInterface $metrics): void
    {
        $key = (string) $metrics->getIdentifier();

        switch (true) {
            case $metrics instanceof FileMetrics:
                if (!is_array($metrics->get('dependencies'))) {
                    break;
                }

                $metrics = $this->handleFile($metrics);
                break;

            case $metrics instanceof ClassMetrics:
                $metrics = $this->handeClass($metrics);
                break;

            case $metrics instanceof FunctionMetrics:
                if (!is_array($metrics->get('dependencies'))) {
                    break;
                }

                $metrics = $this->handleFunction($metrics);
                break;
        }

        $this->metrics->set($key, $metrics);
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
            $abstractness = ($this->abstractClasses[$packageName] + $this->concreteClasses[$packageName]) > 0 ? $this->abstractClasses[$packageName] / ($this->abstractClasses[$packageName] + $this->concreteClasses[$packageName]) : 0;
            $distanceFromMainline = $abstractness + $instability - 1;

            $packageMetric->set('instability', $instability);
            $packageMetric->set('abstractness', $abstractness);
            $packageMetric->set('distanceFromMainline', $distanceFromMainline);

            $this->metrics->set($packageName, $packageMetric);
        }

        foreach ($this->classes as $classId => $className) {
            $metric = $this->metrics->get($classId);

            $usesCount = $metric->get('usesCount');
            $usedByCount = $metric->get('usedByCount');

            $usesForInstabilityCount = $metric->get('usesForInstabilityCount') ?? 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $instability = ($usesForInstabilityCount + $usedByCount) > 0 ? $usesForInstabilityCount / ($usesForInstabilityCount + $usedByCount) : 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $abstractness = $this->calculateClassAbstractness($metric);

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $distanceFromMainline = $abstractness + $instability - 1;

            $metric->set('instability', $instability);
            $metric->set('abstractness', $abstractness);
            $metric->set('distanceFromMainline', $distanceFromMainline);

            $this->metrics->set($classId, $metric);

            $this->usesCount += $usesCount;
            $this->usedByCount += $usedByCount;
            $this->instability += $instability;
        }

        $avgUsesCount = $this->usesCount / count($this->classes);
        $avgUsedByCount = $this->usedByCount / count($this->classes);
        $avgInstability = $this->instability / count($this->classes);

        $overallAbstractness = array_sum($this->abstractClasses) / (array_sum($this->abstractClasses) + array_sum($this->concreteClasses));
        $overallDistanceFromMainline = $overallAbstractness + $avgInstability - 1;

        $projectMetrics = $this->metrics->get('project');

        $projectMetrics->set('OverallAvgUsesCount', $avgUsesCount);
        $projectMetrics->set('OverallAvgUsedByCount', $avgUsedByCount);
        $projectMetrics->set('OverallAvgInstability', $avgInstability);
        $projectMetrics->set('OverallAbstractness', $overallAbstractness);
        $projectMetrics->set('OverallDistanceFromMainline', $overallDistanceFromMainline);
        $this->metrics->set('project', $projectMetrics);
    }

    private function calculateClassAbstractness(ClassMetrics $classMetrics): float
    {
        $abstractCount = count($classMetrics->get('interfaces'));
        $concreteCount = 0;

        foreach ($classMetrics->get('extends') as $className) {
            if (! in_array($className, $this->classes)) {
                continue;
            }

            $classId = array_search($className, $this->classes);
            $dependencyMetrics = $this->metrics->get($classId);
            if ($dependencyMetrics->get('abstract')) {
                ++ $abstractCount;
                continue;
            }

            ++ $concreteCount;
        }

        if ($abstractCount + $concreteCount === 0) {
            return 0;
        }

        return $abstractCount / ($abstractCount + $concreteCount);
    }

    private function handeClass(ClassMetrics $metric): ClassMetrics
    {
        $uses = $metric->get('uses') ?? [];
        $usesCount = $metric->get('usesCount') ?? 0;

        $usesInProject = $metric->get('usesInProject') ?? [];
        $usesInProjectCount = $metric->get('usesInProjectCount') ?? 0;

        $usesForInstability = $metric->get('usesForInstability') ?? 0;

        $usedBy = $metric->get('usedBy') ?? [];
        $usedByCount = $metric->get('usedByCount') ?? 0;

        $package = $metric->get('package');

        if (! isset($this->packagesMap['uses'][$package])) {
            $this->packagesMap['uses'][$package] = [];
        }
        if (! isset($this->packagesMap['usedBy'][$package])) {
            $this->packagesMap['usedBy'][$package] = [];
        }

        if (! isset($this->abstractClasses[$package])) {
            $this->abstractClasses[$package] = 0;
        }

        if (! isset($this->concreteClasses[$package])) {
            $this->concreteClasses[$package] = 0;
        }

        if ($metric->get('realClass') && $metric->get('abstract') || $metric->get('interface')) {
            ++ $this->abstractClasses[$package];
        } elseif ($metric->get('realClass')) {
            ++ $this->concreteClasses[$package];
        }

        foreach ($metric->get('dependencies') as $dependency) {
            if ($metric->getName() === $dependency) {
                continue;
            }

            ++ $usesCount;
            $uses[] = $dependency;

            $checkArray = array_merge($this->classes, $this->interfaces, $this->traits);

            $isTrait = true;

            if (in_array($dependency, $checkArray)) {
                // Counts only dependencies inside current project
                ++ $usesInProjectCount;
                $usesInProject[] = $dependency;

                if (! in_array($dependency, $this->traits)) {
                    $isTrait = false;
                    ++ $usesForInstability;
                }
            }

            $classKey = array_search($dependency, $checkArray);

            if (! $classKey) {
                continue;
            }

            $usedByMetric = $this->metrics->get($classKey);

            if ($package !== $usedByMetric->get('package') && ! $isTrait) {
                if (! in_array($dependency, $this->packagesMap['uses'][$package])) {
                    ++ $this->packages[$package]['usesCount'];
                    $this->packagesMap['uses'][$package][] = $dependency;
                }
                if (! in_array($dependency, $this->packagesMap['usedBy'][$package])) {
                    ++ $this->packages[$usedByMetric->get('package')]['usedByCount'];
                    $this->packagesMap['usedBy'][$package][] = $usedByMetric->getName();
                }
            }

            $classUsedBy = $usedByMetric->get('usedBy') ?? [];
            $classUsedByCount = $usedByMetric->get('usedByCount') ?? 0;

            $classUsedBy[] = $metric->getName();
            ++ $classUsedByCount;

            $usedByMetric->set('usedBy', $classUsedBy);
            $usedByMetric->set('usedByCount', $classUsedByCount);
        }

        $metric->set('uses', $uses);
        $metric->set('usesCount', $usesCount);
        $metric->set('usedBy', $usedBy);
        $metric->set('usedByCount', $usedByCount);
        $metric->set('usesInProject', $usesInProject);
        $metric->set('usesInProjectCount', $usesInProjectCount);
        $metric->set('usesForInstabilityCount', $usesForInstability);

        return $metric;
    }

    private function handleFile(FileMetrics $metric): FileMetrics
    {
        $usedFromOutside = $metric->get('usedFromOutside') ?? [];
        $usedFromOutsideCount = $metric->get('usedFromOutsideCount') ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            $classKey = array_search($dependency, $this->classes);
            if (!$classKey) {
                continue;
            }

            $usedFromOutside[] = $metric->getName();
            ++ $usedFromOutsideCount;
        }

        $metric->set('usedFromOutside', $usedFromOutside);
        $metric->set('usedFromOutsideCount', $usedFromOutsideCount);

        return $metric;
    }

    private function handleFunction(FunctionMetrics $metric): FunctionMetrics
    {
        $usedByFunction = $metric->get('usedByFunction') ?? [];
        $usedByFunctionCount = $metric->get('usedByFunctionCount') ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            $classKey = array_search($dependency, $this->classes);
            if (!$classKey) {
                continue;
            }

            $usedByFunction[] = $metric->getName();
            ++ $usedByFunctionCount;
        }

        $metric->set('usedByFunction', $usedByFunction);
        $metric->set('usedByFunctionCount', $usedByFunctionCount);

        return $metric;
    }
}
