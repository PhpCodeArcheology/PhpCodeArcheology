<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Metrics\Manager\MetricsManager;
use PhpCodeArch\Metrics\Metrics;

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
        private Metrics $metrics,
        private MetricsManager $metricsManager,
        private CliOutput $output)
    {
        foreach ($this->calculators as $calculator) {
            $usedMetricTypes = $this->metricsManager->getMetricTypesByKeys($calculator->getUsedMetricTypeKeys());
            $calculator->setUsedMetricTypes($usedMetricTypes);
        }
    }

    public function run(): void
    {
        $this->maybeCallMethod('beforeTraverse');

        $count = 0;
        $metricCount = number_format(count(array_filter($this->metrics->getAll(), function ($metric) {
            return ! is_array($metric);
        })));
        foreach ($this->metrics->getAll() as $metric) {
            if (is_array($metric)) {
                continue;
            }

            $this->output->cls();
            $this->output->out(
                "Running calculator on metric \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$metricCount\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            foreach ($this->calculators as $calculator) {
                $calculator->calculate($metric);
            }
        }

        $this->maybeCallMethod('afterTraverse');

        $this->output->outNl();
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
