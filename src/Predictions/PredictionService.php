<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\ProgressBar;

class PredictionService
{
    /**
     * @var array<int, int>
     */
    private array $problemCount = [];

    public function __construct(
        /**
         * @var PredictionInterface[]
         */
        private readonly array $predictions,
        private readonly CliOutput $output,
    ) {
        $this->problemCount = [
            PredictionInterface::INFO => 0,
            PredictionInterface::WARNING => 0,
            PredictionInterface::ERROR => 0,
        ];
    }

    public function predict(): void
    {
        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $progressBar = new ProgressBar($this->output, $formatter, count($this->predictions), 'Predicting');

        foreach ($this->predictions as $prediction) {
            $progressBar->advance();

            $this->problemCount[$prediction->getLevel()] += $prediction->predict();
        }

        $progressBar->finish();
    }

    /**
     * @return array<int, int>
     */
    public function getProblemCount(): array
    {
        return $this->problemCount;
    }
}
