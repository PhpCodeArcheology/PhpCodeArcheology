<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class TooDependentPrediction implements PredictionInterface
{

    public function predict(Metrics $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof ClassMetrics:
                case $metric instanceof FunctionMetrics:
                    $maxDependency = $metric instanceof FunctionMetrics ? 10 : 20;

                    $tooDependent = $metric->get('usesCount') > $maxDependency;
                    $metric->set('predictionTooDependent', $tooDependent);

                    if ($tooDependent) {
                        ++ $problemCount;
                    }
                    break;
            }

            $metrics->set($key, $metric);
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::INFO;
    }
}
