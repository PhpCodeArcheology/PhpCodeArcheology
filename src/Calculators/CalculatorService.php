<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

readonly class CalculatorService
{
    public function __construct(
        /**
         * @var CalculatorInterface[]
         */
        private array   $calculators,
        /**
         * @var Metrics
         */
        private Metrics $metrics)
    {
    }

    public function calculate(): void
    {
        foreach ($this->calculators as $calculator) {
            $calculator->calculate($this->metrics);
        }
    }
}
