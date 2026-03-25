<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class GodClassPrediction implements PredictionInterface
{
    use PredictionTrait;

    public function __construct(?Config $config = null)
    {
        $this->config = $config;
    }

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

            $couplingThreshold = 10;
            if ($this->isFrameworkAdjustmentEnabled('controllerThresholds')
                && ($this->isSymfonyDetected() || $this->getFrameworkDetection()?->laravelDetected)
                && fnmatch('*Controller', $metric->getName())
            ) {
                $couplingThreshold = 25;
            }

            if ($classMetrics->usesCount + $classMetrics->usedByCount > $couplingThreshold) {
                ++ $suspectIndex;
            }

            if (($classMetrics->lcom ?? 0) > 1 && !$this->shouldSkipLcom($metricsController, $metric)) {
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
