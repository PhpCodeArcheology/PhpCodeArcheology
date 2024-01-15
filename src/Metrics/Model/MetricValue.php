<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

readonly class MetricValue
{
    private function __construct(private mixed $value, private MetricType $type)
    {
    }

    public static function ofValueAndType(mixed $value, MetricType $type): MetricValue
    {
        return new static($value, $type);
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
}
