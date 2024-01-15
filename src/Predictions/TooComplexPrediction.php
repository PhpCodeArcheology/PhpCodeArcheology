<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;

class TooComplexPrediction implements PredictionInterface
{

    public function predict(MetricsContainer $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                    $methods = $metric->get('methods');

                    $methodCc = 0;
                    foreach ($methods as $methodKey => $method) {
                        $ccValue = $method->get('cc')->getValue();

                        $methodCc += $ccValue;

                        if ($ccValue <= 10) {
                            $method->set('predictionTooComplex', false);
                            continue;
                        }

                        ++ $problemCount;
                        $method->set('predictionTooComplex', true);
                        $methods[$methodKey] = $method;
                    }

                    $avgMethodCc = count($methods) > 0 ? $methodCc / count($methods) : 0;

                    $classTooComplex = $avgMethodCc > 10;

                    if ($classTooComplex) {
                        ++ $problemCount;
                    }

                    $metric->set('methods', $methods);
                    $metric->set('avgMethodCc', $avgMethodCc);
                    $metric->set('predictionTooComplex', $classTooComplex);
                    break;

                case $metric instanceof FileMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxComplexity = $metric->get('lloc')->getValue() > 20 ? 20 : 10;
                    $tooComplex = $metric->get('cc')->getValue() > $maxComplexity;
                    $metric->set('predictionTooComplex', $tooComplex);
                    if ($tooComplex) {
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
        return PredictionInterface::ERROR;
    }
}
