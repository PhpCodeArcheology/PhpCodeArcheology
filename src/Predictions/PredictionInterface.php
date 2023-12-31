<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

interface PredictionInterface
{
    public function predict(Metrics $metrics): void;
}
