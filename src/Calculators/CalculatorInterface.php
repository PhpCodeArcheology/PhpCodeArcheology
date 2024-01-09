<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricsInterface;

interface CalculatorInterface
{
    public function calculate(MetricsInterface $metrics);
}
