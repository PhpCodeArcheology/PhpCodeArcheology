<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Metrics;

trait CalculatorTrait
{
    public function __construct(private readonly Metrics $metrics)
    {
    }
}
