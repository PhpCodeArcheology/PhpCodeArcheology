<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

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
        foreach ($this->predictions as $prediction) {
            $this->problemCount[$prediction->getLevel()] += $prediction->predict($this->metrics);
        }
    }

    public function getProblemCount(): array
    {
        return $this->problemCount;
    }
}
