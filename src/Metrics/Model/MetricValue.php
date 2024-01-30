<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MetricValue
{
    /**
     * @var ProblemInterface[]
     */
    private array $problems = [];

    private readonly MetricType $type;

    private function __construct(
        private readonly mixed $value,
        private readonly string $metricTypeKey)
    {
    }

    public static function ofValueAndTypeKey(mixed $value, string $typeKey): MetricValue
    {
        return new static($value, $typeKey);
    }

    public function getValueFormatted(): mixed
    {
        return match ($this->type->getValueType()) {
            MetricType::VALUE_ARRAY => implode(', ', $this->value),
            MetricType::VALUE_COUNT => count($this->value),
            MetricType::VALUE_FLOAT => round($this->value, 2),
            MetricType::VALUE_PERCENTAGE => number_format($this->value, 2) . '%',
            default => $this->value,
        };
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getSortValue(): mixed
    {
        return match($this->type->getValueType()) {
            MetricType::VALUE_INT, MetricType::VALUE_FLOAT, MetricType::VALUE_PERCENTAGE => $this->value,
            MetricType::VALUE_STRING => strval($this->value),
            MetricType::VALUE_ARRAY, MetricType::VALUE_COUNT => count($this->value),
        };
    }

    public function __toString(): string
    {
        return match($this->type->getValueType()) {
            MetricType::VALUE_INT => number_format($this->value, 0),
            MetricType::VALUE_FLOAT => number_format($this->value, 2),
            MetricType::VALUE_STRING => strval($this->value),
            MetricType::VALUE_ARRAY => implode(', ', $this->value),
            MetricType::VALUE_PERCENTAGE => number_format($this->value, 2) . '%',
            MetricType::VALUE_COUNT => strval(count($this->value)),
        };
    }

    public function setMetricType(MetricType $metricType): void
    {
        $this->type = $metricType;
    }

    public function getMetricType(): MetricType
    {
        return $this->type;
    }

    public function getMetricTypeKey(): string
    {
        return $this->metricTypeKey;
    }

    public function addProblem(ProblemInterface $problem): void
    {
        $this->problems[] = $problem;
    }

    public function getMaxProblemLevel(): int
    {
        $maxProblemLevel = 0;
        foreach ($this->problems as $problem) {
            $maxProblemLevel = max($maxProblemLevel, $problem->getProblemLevel());
        }
        return $maxProblemLevel;
    }

    public function getProblemMessages(): array
    {
        return array_map(fn($problem) => $problem->getMessage(), $this->problems);
    }

    public function getProblems(): array
    {
        return $this->problems;
    }

    public function hasProblems(): bool
    {
        return count($this->problems) > 0;
    }
}
