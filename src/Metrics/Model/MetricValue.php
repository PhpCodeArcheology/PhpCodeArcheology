<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MetricValue implements \Stringable
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
        return new self($value, $typeKey);
    }

    public function getValueFormatted(): mixed
    {
        return match ($this->type->getValueType()) {
            MetricValueType::Array => implode(', ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', $this->asArray())),
            MetricValueType::Count => count($this->asArray()),
            MetricValueType::Float => round($this->asFloat(), 2),
            MetricValueType::Percentage => number_format($this->asFloat(), 2).'%',
            default => $this->value,
        };
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function asInt(): int
    {
        return is_scalar($this->value) || is_array($this->value) ? intval($this->value) : 0;
    }

    public function asFloat(): float
    {
        return is_scalar($this->value) || is_array($this->value) ? floatval($this->value) : 0.0;
    }

    public function asBool(): bool
    {
        return (bool) $this->value;
    }

    public function asString(): string
    {
        if (is_scalar($this->value)) {
            return strval($this->value);
        }

        return '';
    }

    /**
     * @return array<mixed>
     */
    public function asArray(): array
    {
        return (array) $this->value;
    }

    public function getSortValue(): mixed
    {
        return match ($this->type->getValueType()) {
            MetricValueType::Int, MetricValueType::Float, MetricValueType::Percentage => $this->value,
            MetricValueType::String => $this->asString(),
            MetricValueType::Array, MetricValueType::Count => count($this->asArray()),
            default => $this->value,
        };
    }

    public function __toString(): string
    {
        return match ($this->type->getValueType()) {
            MetricValueType::Int => number_format($this->asFloat(), 0),
            MetricValueType::Float => number_format($this->asFloat(), 2),
            MetricValueType::String => $this->asString(),
            MetricValueType::Array => implode(', ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', $this->asArray())),
            MetricValueType::Percentage => number_format($this->asFloat(), 2).'%',
            MetricValueType::Count => strval(count($this->asArray())),
            default => (is_scalar($this->value) ? strval($this->value) : ''),
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

    /** @return string[] */
    public function getProblemMessages(): array
    {
        return array_map(fn (ProblemInterface $problem): string => $problem->getMessage(), $this->problems);
    }

    /** @return ProblemInterface[] */
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
