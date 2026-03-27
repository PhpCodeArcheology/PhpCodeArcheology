<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\ProgressBar;
use PhpCodeArch\Metrics\Controller\MetricsController;

readonly class CalculatorService
{
    public function __construct(
        /**
         * @var CalculatorInterface[]
         */
        private array $calculators,
        private MetricsController $metricsController,
        private CliOutput $output)
    {
    }

    public function run(): void
    {
        $this->maybeCallMethod('beforeTraverse');

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $metricsCollectionCount = $this->metricsController->getContainerCount();
        $progressBar = new ProgressBar($this->output, $formatter, $metricsCollectionCount, 'Calculating');

        foreach ($this->metricsController->getAllCollections() as $metric) {
            $progressBar->advance();

            foreach ($this->calculators as $calculator) {
                $calculator->calculate($metric);
            }
        }

        $this->maybeCallMethod('afterTraverse');

        $progressBar->finish();
    }

    private function maybeCallMethod(string $method): void
    {
        foreach ($this->calculators as $calculator) {
            if (!method_exists($calculator, $method)) {
                continue;
            }

            $calculator->$method();
        }
    }
}
