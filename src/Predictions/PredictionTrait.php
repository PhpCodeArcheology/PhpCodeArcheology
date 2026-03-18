<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;

trait PredictionTrait
{
    private ?Config $config = null;

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

    protected function threshold(string $key, mixed $default): mixed
    {
        $thresholds = $this->config?->get('thresholds') ?? [];
        if (!is_array($thresholds)) {
            return $default;
        }

        $parts = explode('.', $key);
        $value = $thresholds;
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
