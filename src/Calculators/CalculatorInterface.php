<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

interface CalculatorInterface
{
    public function calculate(Metrics $metrics);
}
