<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooDependentProblem;

class TooDependentPrediction implements PredictionInterface
{

    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxDependency = $metric instanceof FunctionMetricsCollection ? 10 : 20;

                    $tooDependent = ($metric->get('usesCount')?->getValue() ?? 0) > $maxDependency;

                    $metricsController->setMetricValueByIdentifierString(
                        (string) $metric->getIdentifier(),
                        'predictionTooDependent',
                        $tooDependent
                    );

                    if (!$tooDependent) {
                        break;
                    }

                    $problem = TooDependentProblem::ofProblemLevelAndMessage(
                        problemLevel: $this->getLevel(),
                        message: 'The element maybe is too dependent, exceeding ' . $maxDependency . ' dependencies.'
                    );

                    $metricsController->setProblemByIdentifierString(
                        identifierString: (string) $metric->getIdentifier(),
                        key: 'uses',
                        problem: $problem
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
