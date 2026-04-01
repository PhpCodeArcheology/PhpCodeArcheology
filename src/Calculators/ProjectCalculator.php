<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class ProjectCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<string, int>> */
    private array $complexData;

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
        $this->complexData = [
            MetricKey::OVERALL_MOST_COMPLEX_FILE => [],
            MetricKey::OVERALL_MOST_COMPLEX_CLASS => [],
            MetricKey::OVERALL_MOST_COMPLEX_METHOD => [],
            MetricKey::OVERALL_MOST_COMPLEX_FUNCTION => [],
        ];

        $this->data = [
            MetricKey::OVERALL_MAX_CC => 0,
            MetricKey::OVERALL_AVG_CC => 0,
            MetricKey::OVERALL_AVG_CC_FILE => 0,
            MetricKey::OVERALL_AVG_CC_CLASS => 0,
            MetricKey::OVERALL_AVG_CC_METHOD => 0,
            MetricKey::OVERALL_AVG_CC_FUNCTION => 0,
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
        if (null === $metrics->get(MetricKey::CC)) {
            return;
        }

        ++$this->metricCount;

        $cc = $metrics->getInt(MetricKey::CC);

        $this->maxCC = max($this->maxCC, $cc);
        $this->sumCC += $cc;
        $this->commentWeightSum += $metrics->getFloat(MetricKey::COMMENT_WEIGHT);

        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $this->complexData[MetricKey::OVERALL_MOST_COMPLEX_FILE][$metrics->getName()] = $cc;
                $this->maxCCFile = max($this->maxCCFile, $cc);
                $this->sumCCFile += $cc;
                $this->miSum += $metrics->getFloat(MetricKey::MAINTAINABILITY_INDEX);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                if ('method' === $metrics->getString(MetricKey::FUNCTION_TYPE)) {
                    break;
                }
                $this->complexData[MetricKey::OVERALL_MOST_COMPLEX_FUNCTION][$metrics->getName()] = $cc;
                $this->maxCCFunction = max($this->maxCCFunction, $cc);
                $this->sumCCFunction += $cc;
                break;

            case $metrics instanceof ClassMetricsCollection:
                $this->complexData[MetricKey::OVERALL_MOST_COMPLEX_CLASS][$metrics->getName()] = $cc;
                $this->maxCCClass = max($this->maxCCClass, $cc);
                $this->sumCCClass += $cc;

                $this->lcomSum += $metrics->getInt(MetricKey::LCOM);

                $methodCollection = $this->metricsController->getCollectionByIdentifierString(
                    (string) $metrics->getIdentifier(),
                    'methods'
                );

                if (null === $methodCollection) {
                    break;
                }

                foreach ($methodCollection->getAsArray() as $methodIdentifierString => $methodName) {
                    if (!is_string($methodIdentifierString) || !is_string($methodName)) {
                        continue;
                    }

                    $methodCC = $this->metricsController->getMetricValueByIdentifierString(
                        $methodIdentifierString,
                        MetricKey::CC
                    )?->asInt() ?? 0;

                    $this->complexData[MetricKey::OVERALL_MOST_COMPLEX_METHOD][$metrics->getName().'::'.$methodName] = $methodCC;
                    $this->maxCCMethod = max($methodCC, $this->maxCCMethod);
                    $this->sumCCMethod += $methodCC;
                }
                break;
        }
    }

    public function afterTraverse(): void
    {
        foreach ($this->complexData as $key => $ccValues) {
            if (empty($ccValues)) {
                $this->data[$key] = '-';
                continue;
            }

            $maxValue = max($ccValues);
            $keysWithMaxValue = array_keys($ccValues, $maxValue);
            $this->data[$key] = sprintf(
                '%s: %d',
                implode(', ', $keysWithMaxValue),
                $maxValue
            );
        }

        $metricValues = $this->metricsController->getMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_FILES,
                MetricKey::OVERALL_CLASSES,
                MetricKey::OVERALL_FUNCTION_COUNT,
                MetricKey::OVERALL_METHODS_COUNT,
            ]
        );

        foreach ($metricValues as &$metricValue) {
            $metricValue = $metricValue?->getValue() ?? 0;
        }

        $this->data[MetricKey::OVERALL_MAX_CC] = $this->maxCC;
        $this->data[MetricKey::OVERALL_MAX_CC_FILE] = $this->maxCCFile;
        $this->data[MetricKey::OVERALL_MAX_CC_CLASS] = $this->maxCCClass;
        $this->data[MetricKey::OVERALL_MAX_CC_METHOD] = $this->maxCCMethod;
        $this->data[MetricKey::OVERALL_MAX_CC_FUNCTION] = $this->maxCCFunction;
        $this->data[MetricKey::OVERALL_AVG_CC] = $this->getAvgOrZero($this->sumCC, $this->metricCount);
        $this->data[MetricKey::OVERALL_AVG_CC_FILE] = $this->getAvgOrZero($this->sumCCFile, $metricValues[MetricKey::OVERALL_FILES]);
        $this->data[MetricKey::OVERALL_AVG_CC_CLASS] = $this->getAvgOrZero($this->sumCCClass, $metricValues[MetricKey::OVERALL_CLASSES]);
        $this->data[MetricKey::OVERALL_AVG_CC_METHOD] = $this->getAvgOrZero($this->sumCCMethod, $metricValues[MetricKey::OVERALL_METHODS_COUNT]);
        $this->data[MetricKey::OVERALL_AVG_CC_FUNCTION] = $this->getAvgOrZero($this->sumCCFunction, $metricValues[MetricKey::OVERALL_FUNCTION_COUNT]);
        $this->data[MetricKey::OVERALL_AVG_LCOM] = $this->getAvgOrZero($this->lcomSum, $metricValues[MetricKey::OVERALL_CLASSES]);
        $this->data[MetricKey::OVERALL_AVG_MI] = $this->getAvgOrZero($this->miSum, $metricValues[MetricKey::OVERALL_FILES]);
        $this->data[MetricKey::OVERALL_COMMENT_WEIGHT] = $this->getAvgOrZero($this->commentWeightSum, $this->metricCount);

        // Ensure counter metrics default to 0 (they are only incremented when items exist)
        $counterDefaults = [
            MetricKey::OVERALL_FUNCTION_COUNT,
            MetricKey::OVERALL_METHODS_COUNT,
            MetricKey::OVERALL_ABSTRACT_CLASSES,
            MetricKey::OVERALL_INTERFACES,
            MetricKey::OVERALL_PUBLIC_METHODS_COUNT,
            MetricKey::OVERALL_PRIVATE_METHODS_COUNT,
            MetricKey::OVERALL_STATIC_METHODS_COUNT,
        ];
        foreach ($counterDefaults as $key) {
            $existing = $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, $key
            );
            if (!$existing instanceof \PhpCodeArch\Metrics\Model\MetricValue || null === $existing->getValue()) {
                $this->data[$key] = 0;
            }
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $this->data
        );
    }

    private function getAvgOrZero(int|float $value, mixed $count): int|float
    {
        if (!is_int($count) || 0 === $count) {
            return 0;
        }

        return $value / $count;
    }
}
