<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;

class CouplingCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private array $classes = [];

    private array $interfaces = [];

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    private int $abstractClasses = 0;

    private int $concreteClasses = 0;

    public function beforeTraverse(): void
    {
        $this->classes = $this->metrics->get('classes');
        $this->interfaces = $this->metrics->get('interfaces');
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
        foreach ($this->classes as $classId => $className) {
            $metric = $this->metrics->get($classId);

            $usesCount = $metric->get('usesCount');
            $usedByCount = $metric->get('usedByCount');

            $usesInProjectCount = $metric->get('usesInProjectCount') ?? 0;
            $usedByInProjectCount = $metric->get('usedByInProjectCount') ?? 0;

            /**
             * @see https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
             */
            $instability = ($usesInProjectCount + $usedByInProjectCount) > 0 ? $usesInProjectCount / ($usesInProjectCount + $usedByInProjectCount) : 0;

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

        $overallAbstractness = $this->abstractClasses / ($this->abstractClasses + $this->concreteClasses);
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
        $usedBy = $metric->get('usedBy') ?? [];
        $usedByCount = $metric->get('usedByCount') ?? 0;

        $usesInProject = $metric->get('usesInProject') ?? [];
        $usesInProjectCount = $metric->get('usesInProjectCount') ?? 0;
        $usedByInProject = $metric->get('usedByInProject') ?? [];
        $usedByInProjectCount = $metric->get('usedByInProjectCount') ?? 0;

        if ($metric->get('realClass') && $metric->get('abstract') || $metric->get('interface')) {
            ++ $this->abstractClasses;
        } elseif ($metric->get('realClass')) {
            ++ $this->concreteClasses;
        }

        foreach ($metric->get('dependencies') as $dependency) {
            if ($metric->getName() === $dependency) {
                continue;
            }

            ++ $usesCount;
            $uses[] = $dependency;

            if (in_array($dependency, $this->classes) || in_array($dependency, $this->interfaces) ) {
                ++ $usesInProjectCount;
                $usesInProject[] = $dependency;
            }

            $classKey = array_search($dependency, $this->classes);
            if (! $classKey) {
                continue;
            }

            $usedBy[] = $metric->getName();
            ++ $usedByCount;

            ++ $usedByInProjectCount;
            $usedByInProject[] = $dependency;
        }

        $metric->set('uses', $uses);
        $metric->set('usesCount', $usesCount);
        $metric->set('usedBy', $usedBy);
        $metric->set('usedByCount', $usedByCount);
        $metric->set('usesInProject', $usesInProject);
        $metric->set('usesInProjectCount', $usesInProjectCount);
        $metric->set('usedByInProject', $usedByInProject);
        $metric->set('usedByInProjectCount', $usedByInProjectCount);

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
