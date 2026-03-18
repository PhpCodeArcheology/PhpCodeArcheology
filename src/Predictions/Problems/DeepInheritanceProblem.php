<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class DeepInheritanceProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): DeepInheritanceProblem
    {
        return new DeepInheritanceProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Deep inheritance hierarchy';
    }

    public function getRecommendation(): string
    {
        return 'Prefer composition over inheritance. Extract shared behavior into traits or services.';
    }
}
