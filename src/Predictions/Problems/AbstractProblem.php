<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions\Problems;

abstract readonly class AbstractProblem implements ProblemInterface
{
    protected function __construct(
        private int $problemLevel,
        private string $message)
    {
    }

    abstract public static function ofProblemLevelAndMessage(int $problemLevel, string $message): ProblemInterface;

    public function getProblemLevel(): int
    {
        return $this->problemLevel;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRecommendation(): string
    {
        return '';
    }

    public function getConfidence(): float
    {
        return 1.0;
    }
}
