<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\MetricsInterface;

class CouplingCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private array $classes = [];

    private array $interfaces = [];

    private array $extends = [];

    private array $traits = [];

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    private array $abstractClasses = [];

    private array $concreteClasses = [];

    public function __construct(
        private readonly Metrics                                  $metrics,
        private readonly PackageInstabilityAbstractnessCalculator $packageCalculator)
    {
    }

    public function beforeTraverse(): void
    {
        $this->classes = $this->metrics->get('classes') ?? [];
        $this->interfaces = $this->metrics->get('interfaces') ?? [];
        $this->traits = $this->metrics->get('traits') ?? [];
        $this->packageCalculator->beforeTraverse();
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
        $this->packageCalculator->afterTraverse();

        foreach ($this->classes as $classId => $className) {
            $metric = $this->metrics->get($classId);

            $usesCount = $metric->get('usesCount');
            $usedByCount = $metric->get('usedByCount');

            $usesForInstabilityCount = $metric->get('usesForInstabilityCount') ?? 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $instability = ($usesForInstabilityCount + $usedByCount) > 0 ? $usesForInstabilityCount / ($usesForInstabilityCount + $usedByCount) : 0;

            $metric->set('instability', $instability);

            $this->metrics->set($classId, $metric);

            $this->usesCount += $usesCount;
            $this->usedByCount += $usedByCount;
            $this->instability += $instability;
        }

        $avgUsesCount = $this->usesCount / count($this->classes);
        $avgUsedByCount = $this->usedByCount / count($this->classes);
        $avgInstability = $this->instability / count($this->classes);

        $abstractClassesCount = array_reduce($this->abstractClasses, function($count, $packageClasses) {
            return $count + count($packageClasses);
        }, 0);

        $concreteClassesCount = array_reduce($this->concreteClasses, function($count, $packageClasses) {
            return $count + count($packageClasses);
        }, 0);

        $overallAbstractness = $abstractClassesCount / ($abstractClassesCount + $concreteClassesCount);
        $overallDistanceFromMainline = $overallAbstractness + $avgInstability - 1;

        $projectMetrics = $this->metrics->get('project');

        $projectMetrics->set('OverallAvgUsesCount', $avgUsesCount);
        $projectMetrics->set('OverallAvgUsedByCount', $avgUsedByCount);
        $projectMetrics->set('OverallAvgInstability', $avgInstability);
        $projectMetrics->set('OverallAbstractness', $overallAbstractness);
        $projectMetrics->set('OverallDistanceFromMainline', $overallDistanceFromMainline);
        $this->metrics->set('project', $projectMetrics);
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

        [$this->abstractClasses, $this->concreteClasses] = $this->packageCalculator->handlePackage($metric);

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

            $this->packageCalculator->handleDependency($dependency, $usedByMetric, $isTrait);

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
        return $this->handleMetric($metric, 'usedFromOutside', 'usedFromOutsideCount');
    }

    private function handleFunction(FunctionMetrics $metric): FunctionMetrics
    {
        return $this->handleMetric($metric, 'usedByFunction', 'usedByFunctionCount');
    }

    private function handleMetric(FileMetrics|FunctionMetrics $metric, string $usedByKey, string $usedByCountKey): FileMetrics|FunctionMetrics
    {
        $usedBy = $metric->get($usedByKey) ?? [];
        $usedByCount = $metric->get($usedByCountKey) ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            $classKey = array_search($dependency, $this->classes);
            if (!$classKey) {
                continue;
            }

            $usedBy[] = $metric->getName();
            ++ $usedByCount;
        }

        $metric->set($usedByKey, $usedBy);
        $metric->set($usedByCountKey, $usedByCount);

        return $metric;
    }
}
