<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooLongProblem extends AbstractProblem
{

    static function ofProblemLevelAndMessage(int $problemLevel, string $message): TooLongProblem
    {
        return new TooLongProblem($problemLevel, $message);
    }
}
