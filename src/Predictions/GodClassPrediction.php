<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricKey;
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
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $suspectIndex = 0;

            $metricsValues = $metricsController->getMetricValuesByIdentifierString(
                (string) $metric->getIdentifier(),
                [
                    MetricKey::PUBLIC_COUNT,
                    MetricKey::USES_COUNT,
                    MetricKey::USED_BY_COUNT,
                    MetricKey::LCOM,
                ]
            );

            $publicCount = $metricsValues[MetricKey::PUBLIC_COUNT]?->asInt() ?? 0;
            $usesCount = $metricsValues[MetricKey::USES_COUNT]?->asInt() ?? 0;
            $usedByCount = $metricsValues[MetricKey::USED_BY_COUNT]?->asInt() ?? 0;
            $lcom = $metricsValues[MetricKey::LCOM]?->asFloat() ?? 0.0;

            if ($publicCount > 10) {
                ++$suspectIndex;
            }

            $couplingThreshold = 10;
            $frameworkDetection = $this->getFrameworkDetection();
            if ($this->isFrameworkAdjustmentEnabled('controllerThresholds')
                && ($this->isSymfonyDetected() || (null !== $frameworkDetection && $frameworkDetection->laravelDetected))
                && fnmatch('*Controller', $metric->getName())
            ) {
                $couplingThreshold = 25;
            }

            if ($usesCount + $usedByCount > $couplingThreshold) {
                ++$suspectIndex;
            }

            if ($lcom > 1 && !$this->shouldSkipLcom($metricsController, $metric)) {
                ++$suspectIndex;
            }

            $methodCollection = $metricsController->getCollectionByIdentifierString(
                (string) $metric->getIdentifier(),
                'methods'
            );

            $hasLongMethods = false;
            if (null !== $methodCollection) {
                foreach ($methodCollection as $methodIdString => $methodName) {
                    if (!is_string($methodIdString)) {
                        continue;
                    }
                    $tooLong = $metricsController->getMetricValueByIdentifierString(
                        $methodIdString,
                        MetricKey::PREDICTION_TOO_LONG
                    );
                    if ($tooLong?->asBool()) {
                        $hasLongMethods = true;
                        break;
                    }
                }
            }
            if ($hasLongMethods) {
                ++$suspectIndex;
            }

            $maybeIsGodObject = $suspectIndex >= 3;

            if ($maybeIsGodObject) {
                ++$problemCount;
            }

            $metricsController->setMetricValuesByIdentifierString(
                (string) $metric->getIdentifier(),
                [
                    MetricKey::PREDICTION_GOD_OBJECT => $maybeIsGodObject,
                    MetricKey::GOD_OBJECT_SUSPECT_INDEX => $suspectIndex,
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
