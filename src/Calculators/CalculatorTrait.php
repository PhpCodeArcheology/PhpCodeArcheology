<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;

trait CalculatorTrait
{
    /**
     * @var string[] Das ist ein Test
     */
    private array $usedMetricTypes;

    public function __construct(
        private readonly MetricsController $metricsController)
    {
    }
}
