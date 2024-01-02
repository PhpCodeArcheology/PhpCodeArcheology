<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;

interface CalculatorInterface
{
    public function calculate(MetricsInterface $metrics);
}
