<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;

trait CalculatorTrait
{
    public function __construct(
        private readonly MetricsController $metricsController)
    {
    }
}
