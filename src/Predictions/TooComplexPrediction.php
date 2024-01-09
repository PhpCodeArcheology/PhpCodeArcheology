<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\Metrics;

class TooComplexPrediction implements PredictionInterface
{

    public function predict(Metrics $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof ClassMetrics:
                    $methods = $metric->get('methods');

                    $methodCc = 0;
                    foreach ($methods as $methodKey => $method) {
                        $methodCc += $method->get('cc');

                        if ($method->get('cc') <= 10) {
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

                case $metric instanceof FileMetrics:
                case $metric instanceof FunctionMetrics:
                    $maxComplexity = $metric->get('lloc') > 20 ? 20 : 10;
                    $tooComplex = $metric->get('cc') > $maxComplexity;
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
