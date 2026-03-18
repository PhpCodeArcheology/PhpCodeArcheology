<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class DeadCodeProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): DeadCodeProblem
    {
        return new DeadCodeProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Dead code';
    }
}
