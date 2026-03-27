<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

readonly class SecuritySmellProblem extends AbstractProblem
{
    public static function ofProblemLevelAndMessage(int $problemLevel, string $message): SecuritySmellProblem
    {
        return new SecuritySmellProblem($problemLevel, $message);
    }

    public function getName(): string
    {
        return 'Security smell';
    }

    public function getRecommendation(): string
    {
        return 'Replace dangerous functions with safe alternatives. Use parameterized queries instead of string concatenation.';
    }
}
