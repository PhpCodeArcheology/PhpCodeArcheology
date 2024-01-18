<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class TooComplexPrediction implements PredictionInterface
{

    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                    $methodCollection = $metricsController->getCollectionByIdentifierString(
                        (string) $metric->getIdentifier(),
                        'methods'
                    );

                    $methodCc = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $ccValue = $metricsController->getMetricValueByIdentifierString(
                            $methodKey,
                            'cc'
                        )->getValue();

                        $methodCc += $ccValue;

                        if ($ccValue <= 10) {
                            $metricsController->setMetricValueByIdentifierString(
                                $methodKey,
                                'predictionTooComplex',
                                false
                            );
                            continue;
                        }

                        ++ $problemCount;
                        $metricsController->setMetricValueByIdentifierString(
                            $methodKey,
                            'predictionTooComplex',
                            true
                        );
                    }

                    $avgMethodCc = count($methodCollection) > 0 ? $methodCc / count($methodCollection) : 0;

                    $classTooComplex = $avgMethodCc > 10;

                    if ($classTooComplex) {
                        ++ $problemCount;
                    }

                    $metricsController->setMetricValuesByIdentifierString(
                        (string) $metric->getIdentifier(),
                        [
                            'avgMethodCc' => $avgMethodCc,
                            'predictionTooComplex' => $classTooComplex,
                        ],
                    );
                    break;

                case $metric instanceof FileMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $metricValues = $metricsController->getMetricValuesByIdentifierString(
                        (string) $metric->getIdentifier(),
                        [
                            'lloc',
                            'cc',
                        ]
                    );
                    $maxComplexity = $metricValues['lloc']->getValue() > 20 ? 20 : 10;
                    $tooComplex = $metricValues['cc']->getValue() > $maxComplexity;

                    $metricsController->setMetricValueByIdentifierString(
                        (string) $metric->getIdentifier(),
                        'predictionTooComplex',
                        $tooComplex
                    );

                    if ($tooComplex) {
                        ++ $problemCount;
                    }
                    break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
