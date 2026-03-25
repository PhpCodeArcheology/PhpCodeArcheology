<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class UntestedComplexCodeProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): UntestedComplexCodeProblem
    {
        return new UntestedComplexCodeProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Untested Complex Code';
    }

    public function getRecommendation(): string
    {
        return 'Add tests before refactoring to prevent regressions.';
    }
}
