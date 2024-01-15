<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Model\MetricsContainer;

trait CalculatorTrait
{
    private array $usedMetricTypes;

    public function __construct(
        private readonly MetricsContainer $metrics,
        /**
         * @var array $usedMetricTypeKeys
         */
        private readonly array            $usedMetricTypeKeys)
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
}
