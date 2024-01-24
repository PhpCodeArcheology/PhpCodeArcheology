<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

abstract readonly class AbstractProblem implements ProblemInterface
{
    protected function __construct(
        private int    $problemLevel,
        private string $message)
    {
    }

    abstract static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface;

    public function getProblemLevel(): int
    {
        return $this->problemLevel;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
