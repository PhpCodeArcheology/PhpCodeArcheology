<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DeepInheritanceProblem;

class DeepInheritancePrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $dit = $metric->get('dit')?->getValue() ?? 0;

            if ($dit >= 4) {
                ++$problemCount;

                $level = $dit >= 6 ? PredictionInterface::ERROR : PredictionInterface::WARNING;

                $problem = DeepInheritanceProblem::ofProblemLevelAndMessage(
                    problemLevel: $level,
                    message: sprintf('Inheritance depth is %d (warning: >= 4, error: >= 6).', $dit)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'dit',
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
