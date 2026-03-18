<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\SolidViolationProblem;

class SolidViolationPrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $violations = $metric->get('solidViolations')?->getValue() ?? [];
            if (empty($violations)) {
                continue;
            }

            ++$problemCount;

            $problem = SolidViolationProblem::ofProblemLevelAndMessage(
                problemLevel: $this->getLevel(),
                message: sprintf('SOLID violation(s): %s', implode(', ', $violations))
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: 'solidViolationCount',
                problem: $problem
            );
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
