<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class TooLongPrediction implements PredictionInterface
{

    public function predict(MetricsContainer $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            if (is_array($metric)
                || $metric instanceof ProjectMetricsCollection
                || $metric instanceof PackageMetricsCollection) {
                continue;
            }

            $maxLloc = match(true) {
                $metric instanceof FileMetricsCollection => 400,
                $metric instanceof ClassMetricsCollection => 300,
                $metric instanceof FunctionMetricsCollection => 40,
            };

            $isTooLong = $metric->get('lloc')->getValue() > $maxLloc;
            $metric->set('predictionTooLong', $isTooLong);
            $metrics->set($key, $metric);

            if ($isTooLong) {
                ++ $problemCount;
            }

            if (! $metric instanceof ClassMetricsCollection) {
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
