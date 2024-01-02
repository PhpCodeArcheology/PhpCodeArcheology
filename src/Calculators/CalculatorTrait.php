<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

trait CalculatorTrait
{
    public function __construct(private readonly Metrics $metrics)
    {
    }
}
