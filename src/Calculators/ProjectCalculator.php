<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;

class ProjectCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private ProjectMetrics $projectMetrics;

    private array $data;
    private int $maxCC;
    private int $maxCCFile;
    private int $maxCCClass;
    private int $maxCCMethod;
    private int $maxCCFunction;
    private int $sumCC;
    private int $sumCCFile;
    private int $sumCCClass;
    private int $sumCCMethod;
    private int $sumCCFunction;
    private int $metricCount;

    private int $lcomSum;

    private float $miSum;
    private float $commentWeightSum;

    public function beforeTraverse(): void
    {
        $this->projectMetrics = $this->metrics->get('project');

        $this->data = [
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

        $this->maxCC = 0;
        $this->maxCCFile = 0;
        $this->maxCCClass = 0;
        $this->maxCCMethod = 0;
        $this->maxCCFunction = 0;

        $this->sumCC = 0;
        $this->sumCCFile = 0;
        $this->sumCCClass = 0;
        $this->sumCCMethod = 0;
        $this->sumCCFunction = 0;

        $this->metricCount = 0;
        $this->lcomSum = 0;
        $this->miSum = 0;
        $this->commentWeightSum = 0;
    }

    public function calculate(MetricsInterface $metrics): void
    {

        ++ $this->metricCount;

        $this->maxCC = $this->maxCC < $metrics->get('cc') ? $metrics->get('cc') : $this->maxCC;
        $this->sumCC += $metrics->get('cc');
        $this->commentWeightSum += $metrics->get('commentWeight');

        switch (true) {
            case $metrics instanceof FileMetrics:
                $this->data['OverallMostComplexFile'][$metrics->getName()] = $metrics->get('cc');
                $this->maxCCFile = $this->maxCCFile < $metrics->get('cc') ? $metrics->get('cc') : $this->maxCCFile;
                $this->sumCCFile += $metrics->get('cc');
                $this->miSum += $metrics->get('maintainabilityIndex');
                break;

            case $metrics instanceof FunctionMetrics:
                $this->data['OverallMostComplexFunction'][$metrics->getName()] = $metrics->get('cc');
                $this->maxCCFunction = $this->maxCCFunction < $metrics->get('cc') ? $metrics->get('cc') : $this->maxCCFunction;
                $this->sumCCFunction += $metrics->get('cc');
                break;

            case $metrics instanceof ClassMetrics:
                $this->data['OverallMostComplexClass'][$metrics->getName()] = $metrics->get('cc');
                $this->maxCCClass = $this->maxCCClass < $metrics->get('cc') ? $metrics->get('cc') : $this->maxCCClass;
                $this->sumCCClass += $metrics->get('cc');

                $this->lcomSum += $metrics->get('lcom');

                foreach ($metrics->get('methods') as $methodMetric) {
                    $this->data['OverallMostComplexMethod'][$metrics->getName() . '::' . $methodMetric->getName()] = $methodMetric->get('cc');
                    $this->maxCCMethod = $this->maxCCMethod < $methodMetric->get('cc') ? $methodMetric->get('cc') : $this->maxCCMethod;
                    $this->sumCCMethod += $metrics->get('cc');
                }
                break;
        }
    }

    public function afterTraverse(): void
    {
        foreach ($this->data as $key => $ccValues) {
            if (empty($ccValues)) {
                if (is_array($ccValues)) {
                    $this->data[$key] = '-';
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

            $this->data[$key] = $output;
        }

        $fileCount = $this->projectMetrics->get('OverallFiles') ?? 0;
        $classCount = $this->projectMetrics->get('OverallClasses') ?? 0;
        $functionCount = $this->projectMetrics->get('OverallFunctions') ?? 0;
        $methodCount = $this->projectMetrics->get('OverallMethods') ?? 0;

        $this->data['OverallMaxCC'] = $this->maxCC;
        $this->data['OverallMaxCCFile'] = $this->maxCCFile;
        $this->data['OverallMaxCCClass'] = $this->maxCCClass;
        $this->data['OverallMaxCCMethod'] = $this->maxCCMethod;
        $this->data['OverallMaxCCFunction'] = $this->maxCCFunction;
        $this->data['OverallAvgCC'] = $this->getAvgOrZero($this->sumCC, $this->metricCount);
        $this->data['OverallAvgCCFile'] = $this->getAvgOrZero($this->sumCCFile, $fileCount);
        $this->data['OverallAvgCCClass'] = $this->getAvgOrZero($this->sumCCClass, $classCount);
        $this->data['OverallAvgCCMethod'] = $this->getAvgOrZero($this->sumCCMethod, $methodCount);
        $this->data['OverallAvgCCFunction'] = $this->getAvgOrZero($this->sumCCFunction, $functionCount);
        $this->data['OverallAvgLcom'] = $this->getAvgOrZero($this->lcomSum, $classCount);
        $this->data['OverallAvgMI'] = $this->getAvgOrZero($this->miSum, $fileCount);
        $this->data['OverallCommentWeight'] = $this->getAvgOrZero($this->commentWeightSum, $this->metricCount);

        foreach ($this->data as $key => $value) {
            $this->projectMetrics->set($key, $value);
        }

        $this->metrics->set('project', $this->projectMetrics);

    }

    private function getAvgOrZero(int|float $value, int $count): int|float
    {
        if ($count === 0) {
            return 0;
        }

        return $value / $count;
    }
}
