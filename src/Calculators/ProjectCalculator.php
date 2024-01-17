<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class ProjectCalculator implements CalculatorInterface
{
    use CalculatorTrait;

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
        $this->data = [
            'overallMostComplexFile' => [],
            'overallMostComplexClass' => [],
            'overallMostComplexMethod' => [],
            'overallMostComplexFunction' => [],
            'overallMaxCC' => 0,
            'overallAvgCC' => 0,
            'overallAvgCCFile' => 0,
            'overallAvgCCClass' => 0,
            'overallAvgCCMethod' => 0,
            'overallAvgCCFunction' => 0,
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

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if ($metrics->get('cc') === null) {
            return;
        }

        ++ $this->metricCount;

        $cc = $metrics->get('cc')->getValue();

        $this->maxCC = max($this->maxCC, $cc);
        $this->sumCC += $cc;
        $this->commentWeightSum += $metrics->get('commentWeight')->getValue();

        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $this->data['overallMostComplexFile'][$metrics->getName()] = $cc;
                $this->maxCCFile = max($this->maxCCFile, $cc);
                $this->sumCCFile += $cc;
                $this->miSum += $metrics->get('maintainabilityIndex')->getValue();
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $this->data['overallMostComplexFunction'][$metrics->getName()] = $cc;
                $this->maxCCFunction = max($this->maxCCFunction, $cc);
                $this->sumCCFunction += $cc;
                break;

            case $metrics instanceof ClassMetricsCollection:
                $this->data['overallMostComplexClass'][$metrics->getName()] = $cc;
                $this->maxCCClass = max($this->maxCCClass, $cc);
                $this->sumCCClass += $cc;

                $this->lcomSum += $metrics->get('lcom')?->getValue() ?? 0;

                $methodCollection = $this->metricsController->getCollectionByIdentifierString(
                    (string) $metrics->getIdentifier(),
                    'methods'
                );

                foreach ($methodCollection as $methodIdentifierString => $methodName) {
                    $methodCC = $this->metricsController->getMetricValueByIdentifierString(
                        $methodIdentifierString,
                        'cc'
                    )->getValue();

                    $this->data['overallMostComplexMethod'][$metrics->getName() . '::' . $methodName] = $methodCC;
                    $this->maxCCMethod = max($methodCC, $this->maxCCMethod);
                    $this->sumCCMethod += $methodCC;
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

        $metricValues = $this->metricsController->getMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallFiles',
                'overallClasses',
                'overallFunctions',
                'overallMethods',
            ]
        );

        foreach ($metricValues as $key => &$metricValue) {
            $metricValue = $metricValue?->getValue() ?? 0;
        }

        $this->data['overallMaxCC'] = $this->maxCC;
        $this->data['overallMaxCCFile'] = $this->maxCCFile;
        $this->data['overallMaxCCClass'] = $this->maxCCClass;
        $this->data['overallMaxCCMethod'] = $this->maxCCMethod;
        $this->data['overallMaxCCFunction'] = $this->maxCCFunction;
        $this->data['overallAvgCC'] = $this->getAvgOrZero($this->sumCC, $this->metricCount);
        $this->data['overallAvgCCFile'] = $this->getAvgOrZero($this->sumCCFile, $metricValues['overallFiles']);
        $this->data['overallAvgCCClass'] = $this->getAvgOrZero($this->sumCCClass, $metricValues['overallClasses']);
        $this->data['overallAvgCCMethod'] = $this->getAvgOrZero($this->sumCCMethod, $metricValues['overallMethods']);
        $this->data['overallAvgCCFunction'] = $this->getAvgOrZero($this->sumCCFunction, $metricValues['overallFunctions']);
        $this->data['overallAvgLcom'] = $this->getAvgOrZero($this->lcomSum, $metricValues['overallClasses']);
        $this->data['overallAvgMI'] = $this->getAvgOrZero($this->miSum, $metricValues['overallFiles']);
        $this->data['overallCommentWeight'] = $this->getAvgOrZero($this->commentWeightSum, $this->metricCount);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $this->data
        );
    }

    private function getAvgOrZero(int|float $value, int $count): int|float
    {
        if ($count === 0) {
            return 0;
        }

        return $value / $count;
    }
}
