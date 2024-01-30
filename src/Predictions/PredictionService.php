<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;

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
        private readonly array $predictions,
        private readonly MetricsController $metricsController,
        private readonly CliOutput $output
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
            $this->output->outWithMemory(
                "Running prediction \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$predCount\033[0m..."
            );

            ++ $count;

            $this->problemCount[$prediction->getLevel()] += $prediction->predict(
                $this->metricsController
            );
        }

        $this->output->outNl();
    }

    public function getProblemCount(): array
    {
        return $this->problemCount;
    }
}
