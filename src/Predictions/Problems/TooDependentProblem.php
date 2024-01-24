<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooDependentProblem extends AbstractProblem
{

    static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface
    {
        return new TooDependentProblem($problemLevel, $message);
    }
}
