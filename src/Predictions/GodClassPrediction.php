<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Repository\RepositoryInterface;

class GodClassPrediction implements PredictionInterface
{
    public function predict(RepositoryInterface $repository): int
    {
        $problemCount = 0;

        foreach ($repository->getAllMetricCollections() as $metric) {
            if (! $metric instanceof ClassMetricsCollection) {
                continue;
            }

            $suspectIndex = 0;

            $classMetrics = (object) $repository->loadMetricValues(
                null,
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

            $methodCollection = $repository->loadCollection(
                null,
                (string) $metric->getIdentifier(),
                'methods'
            );

            foreach ($methodCollection as $methodIdString => $methodName) {
                $tooLong = $repository->loadMetricValue(
                    null,
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

            $repository->saveMetricValues(
                null,
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
