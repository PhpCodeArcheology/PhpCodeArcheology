<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Repository\RepositoryInterface;

trait PredictionTrait
{
    private function createProblem(string $identifierString, string|array $keys, string $problemClass, int $level, string $message, RepositoryInterface $repository): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            /**
             * @var ProblemInterface $problemClass
             */
            $problem = $problemClass::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: $message
            );

            $repository->saveProblem(
                identifier: $identifierString,
                problemKey: $key,
                problem: $problem
            );
        }
    }
}
