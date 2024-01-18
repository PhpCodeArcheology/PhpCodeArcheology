<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class GodClassPrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (! $metric instanceof ClassMetricsCollection) {
                continue;
            }

            $suspectIndex = 0;

            $classMetrics = (object) $metricsController->getMetricValuesByIdentifierString(
                (string) $metric->getIdentifier(),
                [
                    'publicCount',
                    'usesCount',
                    'usedByCount',
                    'lcom',
                ]
            );

            foreach ($classMetrics as &$metricValue) {
                $metricValue = $metricValue?->getValue() ?? 0;
            }

            if ($classMetrics->publicCount > 10) {
                ++ $suspectIndex;
            }

            if ($classMetrics->usesCount + $classMetrics->usedByCount > 10) {
                ++ $suspectIndex;
            }

            if ($classMetrics->lcom ?? 0 > 1) {
                ++ $suspectIndex;
            }

            $methodCollection = $metricsController->getCollectionByIdentifierString(
                (string) $metric->getIdentifier(),
                'methods'
            );

            foreach ($methodCollection as $methodIdString => $methodName) {
                $tooLong = $metricsController->getMetricValueByIdentifierString(
                    $methodIdString,
                    'predictionTooLong'
                );
                if ($tooLong->getValue() === true) {
                    ++ $suspectIndex;
                }
            }

            $maybeIsGodObject = $suspectIndex >= 3;

            if ($maybeIsGodObject) {
                ++ $problemCount;
            }

            $metricsController->setMetricValuesByIdentifierString(
                (string) $metric->getIdentifier(),
                [
                    'predictionGodObject' => $maybeIsGodObject,
                    'godObjectSuspectIndex' => $suspectIndex,
                ]
            );
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
