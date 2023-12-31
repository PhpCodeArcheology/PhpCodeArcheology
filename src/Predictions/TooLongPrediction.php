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

    public function predict(Metrics $metrics): void
    {
        foreach ($metrics->getAll() as $key => $metric) {
            if (is_array($metric) || $metric instanceof ProjectMetrics) {
                continue;
            }

            $maxLloc = match(true) {
                $metric instanceof FileMetrics => 400,
                $metric instanceof ClassMetrics => 300,
                $metric instanceof FunctionMetrics => 40,
            };

            $metric->set('tooLong', ($metric->get('lloc') > $maxLloc));
            $metrics->set($key, $metric);

            if (! $metric instanceof ClassMetrics) {
                continue;
            }

            $methods = $metric->get('methods');
            foreach ($methods as $methodId => $methodMetric) {
                $methodMetric->set('tooLong', $metric->get('lloc') > 30);
                $methods[$methodId] = $methodMetric;
            }
            $metric->set('methods', $methods);
            $metrics->set($key, $metric);
        }
    }
}
