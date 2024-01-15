<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;

class TooDependentPrediction implements PredictionInterface
{

    public function predict(MetricsContainer $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxDependency = $metric instanceof FunctionMetricsCollection ? 10 : 20;

                    $tooDependent = ($metric->get('usesCount')?->getValue() == 0) > $maxDependency;
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
