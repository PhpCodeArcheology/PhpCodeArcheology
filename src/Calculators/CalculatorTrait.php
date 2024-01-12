<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Manager\MetricType;
use PhpCodeArch\Metrics\Manager\MetricValue;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\MetricsInterface;

trait CalculatorTrait
{
    private array $usedMetricTypes;

    public function __construct(
        private readonly Metrics $metrics,
        /**
         * @var array $usedMetricTypeKeys
         */
        private readonly array $usedMetricTypeKeys)
    {
    }

    public function getUsedMetricTypeKeys(): array
    {
        return $this->usedMetricTypeKeys;
    }

    public function setUsedMetricTypes(array $usedMetricTypes): void
    {
        $this->usedMetricTypes = $usedMetricTypes;
    }

    private function setMetricValue(MetricsInterface &$metrics, string $key, mixed $value): void
    {
        $metrics->set($key, MetricValue::ofValueAndType($value, $this->usedMetricTypes[$key]));
    }

    private function setMetricValues(MetricsInterface &$metrics, array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValue($metrics, $key, $value);
        }
    }

}
