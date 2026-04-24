<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class LimitsAndAveragesCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    public const METRIC_KEYS = [
        FileMetricsCollection::class => [
            MetricKey::CC,
            MetricKey::MAINTAINABILITY_INDEX,
            MetricKey::DIFFICULTY,
            MetricKey::EFFORT,
        ],
        FunctionMetricsCollection::class => [
            MetricKey::CC,
            MetricKey::MAINTAINABILITY_INDEX,
            MetricKey::DIFFICULTY,
            MetricKey::EFFORT,
        ],
        ClassMetricsCollection::class => [
            MetricKey::CC,
            MetricKey::MAINTAINABILITY_INDEX,
            MetricKey::DIFFICULTY,
            MetricKey::EFFORT,
            MetricKey::LCOM,
            MetricKey::INSTABILITY,
        ],
    ];

    /** @var array<string, array<string, array<string, int|float>>> */
    private array $data = [];

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!isset(self::METRIC_KEYS[$metrics::class])) {
            return;
        }

        if (!isset($this->data[$metrics::class])) {
            $this->initValues($metrics);
        }

        foreach (self::METRIC_KEYS[$metrics::class] as $key) {
            $value = $metrics->getFloat($key);

            $this->data[$metrics::class]['max'][$key] = max($value, $this->data[$metrics::class]['max'][$key]);
            $this->data[$metrics::class]['min'][$key] = min($value, $this->data[$metrics::class]['min'][$key]);
            $this->data[$metrics::class]['sum'][$key] += $value;
            ++$this->data[$metrics::class]['cnt'][$key];
            $this->data[$metrics::class]['avg'][$key] = $this->data[$metrics::class]['sum'][$key] / $this->data[$metrics::class]['cnt'][$key];
        }
    }

    public function afterTraverse(): void
    {
        foreach ($this->data as $metricClass => $valueTypes) {
            foreach ($valueTypes as $valueType => $data) {
                if (in_array($valueType, ['cnt', 'sum'])) {
                    continue;
                }

                $className = basename(str_replace('\\', '/', $metricClass));

                foreach ($data as $key => $value) {
                    $projectKey = 'overall'.$className.ucfirst((string) $valueType).ucfirst((string) $key);

                    $this->writer->setMetricValue(
                        MetricCollectionTypeEnum::ProjectCollection,
                        null,
                        $value,
                        $projectKey
                    );
                }
            }
        }
    }

    private function initValues(MetricsCollectionInterface $metrics): void
    {
        $this->data[$metrics::class] = [
            'max' => [],
            'min' => [],
            'avg' => [],
            'sum' => [],
            'cnt' => [],
        ];

        foreach (self::METRIC_KEYS[$metrics::class] as $key) {
            $this->data[$metrics::class]['max'][$key] = 0;
            $this->data[$metrics::class]['min'][$key] = PHP_INT_MAX;
            $this->data[$metrics::class]['avg'][$key] = 0;
            $this->data[$metrics::class]['sum'][$key] = 0;
            $this->data[$metrics::class]['cnt'][$key] = 0;
        }
    }
}
