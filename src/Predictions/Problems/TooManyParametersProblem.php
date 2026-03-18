<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooManyParametersProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): TooManyParametersProblem
    {
        return new TooManyParametersProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Too many parameters';
    }
}
