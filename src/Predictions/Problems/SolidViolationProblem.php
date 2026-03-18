<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class SolidViolationProblem extends AbstractProblem
{
    static function ofProblemLevelAndMessage(int $problemLevel, string $message): SolidViolationProblem
    {
        return new SolidViolationProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'SOLID violation';
    }
}
