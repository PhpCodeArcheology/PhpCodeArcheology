<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MetricValue
{
    /**
     * @var ProblemInterface[]
     */
    private array $problems = [];

    private MetricType $type;

    private object $delta;
    private bool $hasDelta = false;

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
            MetricValueType::Array => implode(', ', $this->value),
            MetricValueType::Count => count($this->value),
            MetricValueType::Float => round($this->value, 2),
            MetricValueType::Percentage => number_format($this->value, 2) . '%',
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
            MetricValueType::Int, MetricValueType::Float, MetricValueType::Percentage => $this->value,
            MetricValueType::String => strval($this->value),
            MetricValueType::Array, MetricValueType::Count => count($this->value),
            default => $this->value,
        };
    }

    public function __toString(): string
    {
        return match($this->type->getValueType()) {
            MetricValueType::Int => number_format($this->value, 0),
            MetricValueType::Float => number_format($this->value, 2),
            MetricValueType::String => strval($this->value),
            MetricValueType::Array => implode(', ', $this->value),
            MetricValueType::Percentage => number_format($this->value, 2) . '%',
            MetricValueType::Count => strval(count($this->value)),
            default => strval($this->value),
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

    public function getDelta(): object
    {
        return $this->delta;
    }

    public function setDelta(object $delta): void
    {
        $this->delta = $delta;
        $this->hasDelta = true;
    }

    public function getHasDelta(): bool
    {
        return $this->hasDelta;
    }
}
