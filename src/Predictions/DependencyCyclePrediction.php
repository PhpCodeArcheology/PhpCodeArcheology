<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DependencyCycleProblem;

class DependencyCyclePrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $inCycle = $metric->get('inDependencyCycle')?->getValue() ?? false;

            if ($inCycle) {
                ++$problemCount;

                $cycleClasses = $metric->get('dependencyCycleClasses')?->getValue() ?? [];
                $cycleLength = $metric->get('dependencyCycleLength')?->getValue() ?? 0;

                $problem = DependencyCycleProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: sprintf(
                        'Part of a circular dependency (cycle length: %d, classes: %s).',
                        $cycleLength,
                        implode(', ', array_slice($cycleClasses, 0, 5))
                    )
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'inDependencyCycle',
                    problem: $problem
                );
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
