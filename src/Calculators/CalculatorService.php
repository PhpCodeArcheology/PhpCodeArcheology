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

    public function run(): void
    {
        $this->maybeCallMethod('beforeTraverse');

        foreach ($this->metrics->getAll() as $metric) {
            if (is_array($metric)) {
                continue;
            }

            foreach ($this->calculators as $calculator) {
                $calculator->calculate($metric);
            }
        }

        $this->maybeCallMethod('afterTraverse');
    }

    private function maybeCallMethod(string $method): void
    {
        foreach ($this->calculators as $calculator) {
            if (! method_exists($calculator, $method)) {
                continue;
            }

            $calculator->$method();
        }
    }
}
