<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooDependentProblem;
use PhpCodeArch\Repository\RepositoryInterface;

class TooDependentPrediction implements PredictionInterface
{

    public function predict(RepositoryInterface $repository): int
    {
        $problemCount = 0;

        foreach ($repository->getAllMetricCollections() as $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxDependency = $metric instanceof FunctionMetricsCollection ? 10 : 20;

                    $tooDependent = ($metric->get('usesCount')?->getValue() ?? 0) > $maxDependency;

                    $repository->saveMetricValue(
                        null,
                        (string) $metric->getIdentifier(),
                        $tooDependent,
                        'predictionTooDependent'
                    );

                    if (!$tooDependent) {
                        break;
                    }

                    $problem = TooDependentProblem::ofProblemLevelAndMessage(
                        problemLevel: $this->getLevel(),
                        message: 'The element maybe is too dependent, exceeding ' . $maxDependency . ' dependencies.'
                    );

                    $repository->saveProblem(
                        (string) $metric->getIdentifier(),
                        'uses',
                        $problem
                    );

                    ++ $problemCount;
                    break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::INFO;
    }
}
