<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class TooLongProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): TooLongProblem
    {
        return new TooLongProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Too long';
    }

    public function getRecommendation(): string
    {
        return 'Extract Method or split into smaller units. Each method should do one thing.';
    }
}
