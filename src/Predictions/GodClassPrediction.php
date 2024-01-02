<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class GodClassPrediction implements PredictionInterface
{
    public function predict(Metrics $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $metric) {
            if (! $metric instanceof ClassMetrics) {
                continue;
            }

            $suspectIndex = 0;

            if ($metric->get('publicMethods') > 10) {
                ++ $suspectIndex;
            }

            if ($metric->get('usesCount') + $metric->get('usedByCount') > 10) {
                ++ $suspectIndex;
            }

            if ($metric->get('lcom') > 1) {
                ++ $suspectIndex;
            }

            $methods = $metric->get('methods');
            foreach ($methods as $method) {
                if ($method->get('tooLong') === true) {
                    ++ $suspectIndex;
                }
            }

            $maybeIsGodObject = $suspectIndex >= 3;

            if ($maybeIsGodObject) {
                ++ $problemCount;
            }

            $metric->set('godObject', $maybeIsGodObject);
            $metric->set('godObjectSuspectIndex', $suspectIndex);
            $metrics->set((string) $metric->getIdentifier(), $metric);
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
