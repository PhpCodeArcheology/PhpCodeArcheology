<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class LowTypeCoverageProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): LowTypeCoverageProblem
    {
        return new LowTypeCoverageProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Low type coverage';
    }

    public function getRecommendation(): string
    {
        return 'Add type declarations to parameters, return types, and properties.';
    }
}
