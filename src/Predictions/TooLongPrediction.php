<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;

class TooLongPrediction implements PredictionInterface
{

    public function predict(Metrics $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            if (is_array($metric) || $metric instanceof ProjectMetrics) {
                continue;
            }

            $maxLloc = match(true) {
                $metric instanceof FileMetrics => 400,
                $metric instanceof ClassMetrics => 300,
                $metric instanceof FunctionMetrics => 40,
            };

            $isTooLong = $metric->get('lloc') > $maxLloc;
            $metric->set('predictionTooLong', $isTooLong);
            $metrics->set($key, $metric);

            if ($isTooLong) {
                ++ $problemCount;
            }

            if (! $metric instanceof ClassMetrics) {
                continue;
            }

            $methods = $metric->get('methods');
            foreach ($methods as $methodId => $methodMetric) {
                $isTooLong = $methodMetric->get('lloc') > 30;
                $methodMetric->set('predictionTooLong', $isTooLong);
                $methods[$methodId] = $methodMetric;

                if ($isTooLong) {
                    ++ $problemCount;
                }
            }
            $metric->set('methods', $methods);
            $metrics->set($key, $metric);
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}