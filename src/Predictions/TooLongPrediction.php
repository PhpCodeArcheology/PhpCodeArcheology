<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooLongProblem;

class TooLongPrediction implements PredictionInterface
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
            if (is_array($metric)
                || $metric instanceof ProjectMetricsCollection
                || $metric instanceof PackageMetricsCollection) {
                continue;
            }

            $maxLloc = match(true) {
                $metric instanceof FileMetricsCollection => $this->threshold('tooLong.file', 400),
                $metric instanceof ClassMetricsCollection => $this->threshold('tooLong.class', 300),
                $metric instanceof FunctionMetricsCollection => $this->threshold('tooLong.function', 40),
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

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code.'
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'lloc',
                    problem: $problem
                );
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

                $isTooLong = $lloc->getValue() > $this->threshold('tooLong.method', 30);

                $metricsController->setMetricValueByIdentifierString(
                    $methodIdString,
                    'predictionTooLong',
                    $isTooLong
                );

                if (! $isTooLong) {
                    continue;
                }

                ++ $problemCount;

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code.'
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: $methodIdString,
                    key: 'lloc',
                    problem: $problem
                );

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code in at least one method.'
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'lloc',
                    problem: $problem
                );

            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
