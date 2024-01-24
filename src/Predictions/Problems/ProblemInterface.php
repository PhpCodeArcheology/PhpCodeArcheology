<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

interface ProblemInterface
{
    public static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface;

    public function getProblemLevel(): int;
    public function getMessage(): string;
}
