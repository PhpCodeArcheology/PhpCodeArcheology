<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class ProjectCalculator implements CalculatorInterface
{

    public function calculate(Metrics $metrics): void
    {
        $projectMetrics = $metrics->get('project');

        $data = [
            'OverallMostComplexFile' => [],
            'OverallMostComplexClass' => [],
            'OverallMostComplexMethod' => [],
            'OverallMostComplexFunction' => [],
            'OverallMaxCC' => 0,
            'OverallAvgCC' => 0,
            'OverallAvgCCFile' => 0,
            'OverallAvgCCClass' => 0,
            'OverallAvgCCMethod' => 0,
            'OverallAvgCCFunction' => 0,
        ];

        $maxCC = 0;
        $maxCCFile = 0;
        $maxCCClass = 0;
        $maxCCMethod = 0;
        $maxCCFunction = 0;

        $sumCC = 0;
        $sumCCFile = 0;
        $sumCCClass = 0;
        $sumCCMethod = 0;
        $sumCCFunction = 0;

        $metricCount = 0;
        foreach ($metrics->getAll() as $metric) {
            if (is_array($metric)) {
                continue;
            }

            ++ $metricCount;

            $maxCC = $maxCC < $metric->get('cc') ? $metric->get('cc') : $maxCC;
            $sumCC += $metric->get('cc');

            switch (true) {
                case $metric instanceof FileMetrics:
                    $data['OverallMostComplexFile'][$metric->getName()] = $metric->get('cc');
                    $maxCCFile = $maxCCFile < $metric->get('cc') ? $metric->get('cc') : $maxCCFile;
                    $sumCCFile += $metric->get('cc');
                    break;

                case $metric instanceof FunctionMetrics:
                    $data['OverallMostComplexFunction'][$metric->getName()] = $metric->get('cc');
                    $maxCCFunction = $maxCCFunction < $metric->get('cc') ? $metric->get('cc') : $maxCCFunction;
                    $sumCCFunction += $metric->get('cc');
                    break;

                case $metric instanceof ClassMetrics:
                    $data['OverallMostComplexClass'][$metric->getName()] = $metric->get('cc');
                    $maxCCClass = $maxCCClass < $metric->get('cc') ? $metric->get('cc') : $maxCCClass;
                    $sumCCClass += $metric->get('cc');

                    foreach ($metric->get('methods') as $methodMetric) {
                        $data['OverallMostComplexMethod'][$metric->getName() . '::' . $methodMetric->getName()] = $methodMetric->get('cc');
                        $maxCCMethod = $maxCCMethod < $metric->get('cc') ? $metric->get('cc') : $maxCCMethod;
                        $sumCCMethod += $metric->get('cc');
                    }
                    break;
            }
        }

        foreach ($data as $key => $ccValues) {
            if (empty($ccValues)) {
                if (is_array($ccValues)) {
                    $data[$key] = '-';
                }
                continue;
            }

            $maxValue = max($ccValues);
            $keysWithMaxValue = array_keys($ccValues, $maxValue);
            $output = sprintf(
                '%s: %d',
                implode(', ', $keysWithMaxValue),
                $maxValue
            );

            $data[$key] = $output;
        }

        $fileCount = $projectMetrics->get('OverallFiles') ?? 0;
        $classCount = $projectMetrics->get('OverallClasses') ?? 0;
        $functionCount = $projectMetrics->get('OverallFunctions') ?? 0;
        $methodCount = $projectMetrics->get('OverallMethods') ?? 0;

        $data['OverallMaxCC'] = $maxCC;
        $data['OverallMaxCCFile'] = $maxCCFile;
        $data['OverallMaxCCClass'] = $maxCCClass;
        $data['OverallMaxCCMethod'] = $maxCCMethod;
        $data['OverallMaxCCFunction'] = $maxCCFunction;
        $data['OverallAvgCC'] = $this->getAvgOrZero($sumCC, $metricCount);
        $data['OverallAvgCCFile'] = $this->getAvgOrZero($sumCCFile, $fileCount);
        $data['OverallAvgCCClass'] = $this->getAvgOrZero($sumCCClass, $classCount);
        $data['OverallAvgCCMethod'] = $this->getAvgOrZero($sumCCMethod, $methodCount);
        $data['OverallAvgCCFunction'] = $this->getAvgOrZero($sumCCFunction, $functionCount);

        foreach ($data as $key => $value) {
            $projectMetrics->set($key, $value);
        }

        $metrics->set('project', $projectMetrics);
    }

    private function getAvgOrZero(int $value, int $count): int|float
    {
        if ($count === 0) {
            return 0;
        }

        return $value / $count;
    }
}
