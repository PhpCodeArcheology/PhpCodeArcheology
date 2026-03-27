<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class SolidViolationProblem extends AbstractProblem
{
    public static function ofProblemLevelAndMessage(int $problemLevel, string $message): SolidViolationProblem
    {
        return new SolidViolationProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'SOLID violation';
    }

    public function getRecommendation(): string
    {
        return 'SRP: Split class responsibilities. ISP: Break large interfaces into smaller ones. DIP: Depend on abstractions.';
    }
}
