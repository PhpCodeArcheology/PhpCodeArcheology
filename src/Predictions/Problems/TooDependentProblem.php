<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooDependentProblem extends AbstractProblem
{
    public static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface
    {
        return new TooDependentProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Too dependent';
    }

    public function getRecommendation(): string
    {
        return 'Introduce interfaces to reduce coupling. Consider dependency injection.';
    }
}
