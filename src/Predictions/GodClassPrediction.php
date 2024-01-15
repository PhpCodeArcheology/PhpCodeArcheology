<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;

class GodClassPrediction implements PredictionInterface
{
    public function predict(MetricsContainer $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $metric) {
            if (! $metric instanceof ClassMetricsCollection) {
                continue;
            }

            $suspectIndex = 0;

            if ($metric->get('publicMethods') > 10) {
                ++ $suspectIndex;
            }

            if ($metric->get('usesCount')->getValue() + $metric->get('usedByCount')->getValue() > 10) {
                ++ $suspectIndex;
            }

            if ($metric->get('lcom')?->getValue() ?? 0 > 1) {
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

            $metric->set('predictionGodObject', $maybeIsGodObject);
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
