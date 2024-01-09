<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Application\CliOutput;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class PredictionService
{
    /**
     * @var int[]
     */
    private array $problemCount = [];

    public function __construct(
        /**
         * @var PredictionInterface[]
         */
        private readonly array   $predictions,

        /**
         * @var Metrics
         */
        private readonly Metrics $metrics,
        private CliOutput $output
    )
    {
        $this->problemCount = [
            PredictionInterface::INFO => 0,
            PredictionInterface::WARNING => 0,
            PredictionInterface::ERROR => 0,
        ];
    }

    public function predict(): void
    {
        $count = 0;
        $predCount = number_format(count($this->predictions));
        foreach ($this->predictions as $prediction) {
            $this->output->cls();
            $this->output->out(
                "Running prediction \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$predCount\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            $this->problemCount[$prediction->getLevel()] += $prediction->predict($this->metrics);
        }

        $this->output->outNl();
    }

    public function getProblemCount(): array
    {
        return $this->problemCount;
    }
}
