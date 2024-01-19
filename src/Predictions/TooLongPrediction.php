<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class TooLongPrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
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

            $lloc = $metric->get('lloc')?->getValue() ?? 0;
            $isTooLong = $lloc > $maxLloc;

            $metricsController->setMetricValueByIdentifierString(
                (string) $metric->getIdentifier(),
                'predictionTooLong',
                $isTooLong
            );

            if ($isTooLong) {
                ++ $problemCount;
            }

            if (! $metric instanceof ClassMetricsCollection) {
                continue;
            }

            $methodCollection = $metricsController->getCollectionByIdentifierString(
                (string) $metric->getIdentifier(),
                'methods'
            );

            foreach ($methodCollection as $methodIdString => $methodName) {
                $lloc = $metricsController->getMetricValueByIdentifierString(
                    $methodIdString,
                    'lloc'
                );
                $isTooLong = $lloc->getValue() > 30;

                $metricsController->setMetricValueByIdentifierString(
                    $methodIdString,
                    'predictionTooLong',
                    $isTooLong
                );

                if ($isTooLong) {
                    ++ $problemCount;
                }
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
