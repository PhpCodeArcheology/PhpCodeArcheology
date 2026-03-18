<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DeadCodeProblem;

class DeadCodePrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $unusedCount = $metric->get('unusedPrivateMethodCount')?->getValue() ?? 0;

            if ($unusedCount > 0) {
                ++$problemCount;

                $unusedMethods = $metric->get('unusedPrivateMethods')?->getValue() ?? [];

                $problem = DeadCodeProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: sprintf(
                        '%d unused private method(s): %s',
                        $unusedCount,
                        implode(', ', array_slice($unusedMethods, 0, 5))
                    )
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'unusedPrivateMethodCount',
                    problem: $problem
                );
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::INFO;
    }
}
