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

    private int $usedByCount = 0;

    private int $usesCount = 0;

    private float $instability = 0;

    public function beforeTraverse(): void
    {
        $this->classes = $this->metrics->get('classes');
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

        $metrics->set($key, $metrics);
    }

    public function afterTraverse(): void
    {
        foreach ($this->classes as $classId => $className) {
            $metric = $this->metrics->get($classId);

            $usesCount = $metric->get('usesCount');
            $usedByCount = $metric->get('usedByCount');

            $instability = ($usesCount + $usedByCount) > 0 ? $usesCount / ($usesCount + $usedByCount) : 0;
            $metric->set('instability', $instability);
            $this->metrics->set($classId, $metric);

            $this->usesCount += $usesCount;
            $this->usedByCount += $usedByCount;
            $this->instability += $instability;
        }

        $avgUsesCount = $this->usesCount / count($this->classes);
        $avgUsedByCount = $this->usedByCount / count($this->classes);
        $avgInstability = $this->instability / count($this->classes);

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('OverallAvgUsesCount', $avgUsesCount);
        $projectMetrics->set('OverallAvgUsedByCount', $avgUsedByCount);
        $projectMetrics->set('OverallAvgInstability', $avgInstability);
        $this->metrics->set('project', $projectMetrics);
    }

    private function handeClass(ClassMetrics $metric): ClassMetrics
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

            $classKey = array_search($dependency, $this->classes);
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
