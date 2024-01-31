<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooLongProblem;
use PhpCodeArch\Repository\RepositoryInterface;

class TooLongPrediction implements PredictionInterface
{
    public function predict(RepositoryInterface $repository): int
    {
        $problemCount = 0;

        foreach ($repository->getAllMetricCollections() as $metric) {
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

            $repository->saveMetricValue(
                null,
                (string) $metric->getIdentifier(),
                $isTooLong,
                'predictionTooLong'
            );

            if ($isTooLong) {
                ++ $problemCount;

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code.'
                );

                $repository->saveProblem(
                    (string) $metric->getIdentifier(),
                    'lloc',
                    $problem
                );
            }

            if (! $metric instanceof ClassMetricsCollection) {
                continue;
            }

            $methodCollection = $repository->loadCollection(
                null,
                (string) $metric->getIdentifier(),
                'methods'
            );

            foreach ($methodCollection as $methodIdString => $methodName) {
                $lloc = $repository->loadMetricValue(
                    null,
                    $methodIdString,
                    'lloc'
                );

                $isTooLong = $lloc->getValue() > 30;

                $repository->saveMetricValue(
                    null,
                    $methodIdString,
                    $isTooLong,
                    'predictionTooLong'
                );

                if (! $isTooLong) {
                    continue;
                }

                ++ $problemCount;

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code.'
                );

                $repository->saveProblem(
                    $methodIdString,
                    'lloc',
                    $problem
                );

                $problem = TooLongProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: 'Too many logical lines of code in at least one method.'
                );

                $repository->saveProblem(
                    (string) $metric->getIdentifier(),
                    'lloc',
                    $problem
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
