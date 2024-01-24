<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;

trait PredictionTrait
{
    private function createProblem(string $identifierString, string|array $keys, string $problemClass, int $level, string $message, MetricsController $metricsController): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            $problem = TooComplexProblem::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: $message
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: $identifierString,
                key: $key,
                problem: $problem
            );
        }
    }
}
