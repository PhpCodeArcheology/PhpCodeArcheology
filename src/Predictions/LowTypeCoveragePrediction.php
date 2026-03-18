<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\LowTypeCoverageProblem;

class LowTypeCoveragePrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $typeCoverage = $metric->get('typeCoverage')?->getValue() ?? 100;

            if ($typeCoverage < 40) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::ERROR,
                    message: sprintf('Type coverage is critically low at %.1f%% (threshold: 40%%).', $typeCoverage)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'typeCoverage',
                    problem: $problem
                );
            } elseif ($typeCoverage < 60) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::WARNING,
                    message: sprintf('Type coverage is only %.1f%% (threshold: 60%%).', $typeCoverage)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'typeCoverage',
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
