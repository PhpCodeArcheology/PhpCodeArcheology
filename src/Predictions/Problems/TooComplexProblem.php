<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooComplexProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface
    {
        return new TooComplexProblem($problemLevel, $message);
    }
}
