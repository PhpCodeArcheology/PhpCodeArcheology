<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;

readonly class CalculatorService
{
    public function __construct(
        /**
         * @var CalculatorInterface[]
         */
        private array             $calculators,
        /**
         * @var MetricsContainer
         */
        private MetricsController $metricsController,
        private CliOutput         $output)
    {
    }

    public function run(): void
    {
        $this->maybeCallMethod('beforeTraverse');

        $count = 0;

        $metricsCollectionCount = $this->metricsController->getContainerCount();

        foreach ($this->metricsController->getAllCollections() as $metric) {
            $this->output->cls();
            $this->output->outWithMemory(
                "Running calculator on metric \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$metricsCollectionCount\033[0m..."
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
