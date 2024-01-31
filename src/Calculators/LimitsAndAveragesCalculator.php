<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class LimitsAndAveragesCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    const METRIC_KEYS = [
        FileMetricsCollection::class => [
            'cc',
            'maintainabilityIndex',
            'difficulty',
            'effort',
        ],
        FunctionMetricsCollection::class => [
            'cc',
            'maintainabilityIndex',
            'difficulty',
            'effort',
        ],
        ClassMetricsCollection::class => [
            'cc',
            'maintainabilityIndex',
            'difficulty',
            'effort',
            'lcom',
            'instability',
        ],
    ];

    private array $data = [];

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!isset(self::METRIC_KEYS[get_class($metrics)])) {
            return;
        }

        if (!isset($this->data[get_class($metrics)])) {
            $this->initValues($metrics);
        }

        foreach (self::METRIC_KEYS[get_class($metrics)] as $key) {
            $value = $metrics->get($key)?->getValue() ?? 0;

            $this->data[get_class($metrics)]['max'][$key] = max($value, $this->data[get_class($metrics)]['max'][$key]);
            $this->data[get_class($metrics)]['min'][$key] = min($value, $this->data[get_class($metrics)]['min'][$key]);
            $this->data[get_class($metrics)]['sum'][$key] += $value;
            $this->data[get_class($metrics)]['cnt'][$key] ++;
            $this->data[get_class($metrics)]['avg'][$key] = $this->data[get_class($metrics)]['sum'][$key] / $this->data[get_class($metrics)]['cnt'][$key];
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
                    $projectKey = 'overall' . $className .  ucfirst($valueType) . ucfirst($key);

                    $this->repository->saveMetricValue(
                        MetricCollectionTypeEnum::ProjectCollection,
                        null,
                        $value,
                        $projectKey
                    );
                }
            }
        }
    }

    /**
     * @param MetricsCollectionInterface $metrics
     * @return void
     */
    private function initValues(MetricsCollectionInterface $metrics): void
    {
        $this->data[get_class($metrics)] = [
            'max' => [],
            'min' => [],
            'avg' => [],
            'sum' => [],
            'cnt' => [],
        ];

        foreach (self::METRIC_KEYS[get_class($metrics)] as $key) {
            $this->data[get_class($metrics)]['max'][$key] = 0;
            $this->data[get_class($metrics)]['min'][$key] = PHP_INT_MAX;
            $this->data[get_class($metrics)]['avg'][$key] = 0;
            $this->data[get_class($metrics)]['sum'][$key] = 0;
            $this->data[get_class($metrics)]['cnt'][$key] = 0;
        }
    }
}
