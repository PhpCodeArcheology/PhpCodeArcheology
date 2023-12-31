<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class CouplingCalculator implements CalculatorInterface
{
    public function calculate(Metrics $metrics): void
    {
        $classes = $metrics->get('classes');

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof FileMetrics:
                    if (!is_array($metric->get('dependencies'))) {
                        break;
                    }

                    $metric = $this->handleFile($metric, $classes);
                    break;

                case $metric instanceof ClassMetrics:
                    $metric = $this->handeClass($metric, $classes);
                    break;

                case $metric instanceof FunctionMetrics:
                    if (!is_array($metric->get('dependencies'))) {
                        break;
                    }

                    $metric = $this->handleFunction($metric, $classes);
                    break;
            }

            $metrics->set($key, $metric);
        }


        foreach ($classes as $classId => $className) {
            $metric = $metrics->get($classId);

            $usesCount = $metric->get('usesCount');
            $usedByCount = $metric->get('usedByCount');

            $instability = ($usesCount + $usedByCount) > 0 ? $usesCount / ($usesCount + $usedByCount) : 0;
            $metric->set('instability', $instability);
            $metrics->set($classId, $metric);
        }
    }

    private function handeClass(ClassMetrics $metric, array $classes): ClassMetrics
    {
        $uses = $metric->get('uses') ?? [];
        $usesCount = $metric->get('usesCount') ?? 0;
        $usedBy = $metric->get('usedBy') ?? [];
        $usedByCount = $metric->get('usedByCount') ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            if ($metric->getName() === $dependency) {
                continue;
            }

            ++ $usesCount;
            $uses[] = $dependency;

            $classKey = array_search($dependency, $classes);
            if (! $classKey) {
                continue;
            }

            $usedBy[] = $metric->getName();
            ++ $usedByCount;
        }

        $metric->set('uses', $uses);
        $metric->set('usesCount', $usesCount);
        $metric->set('usedBy', $usedBy);
        $metric->set('usedByCount', $usedByCount);

        return $metric;
    }

    private function handleFile(FileMetrics $metric, array $classes): FileMetrics
    {
        $usedFromOutside = $metric->get('usedFromOutside') ?? [];
        $usedFromOutsideCount = $metric->get('usedFromOutsideCount') ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            $classKey = array_search($dependency, $classes);
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

    private function handleFunction(FunctionMetrics $metric, array $classes): FunctionMetrics
    {
        $usedByFunction = $metric->get('usedByFunction') ?? [];
        $usedByFunctionCount = $metric->get('usedByFunctionCount') ?? 0;

        foreach ($metric->get('dependencies') as $dependency) {
            $classKey = array_search($dependency, $classes);
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
