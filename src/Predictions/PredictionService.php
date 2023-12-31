<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class PredictionService
{
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
    }

    public function predict(): void
    {
        foreach ($this->predictions as $prediction) {
            $prediction->predict($this->metrics);
        }
    }
}
