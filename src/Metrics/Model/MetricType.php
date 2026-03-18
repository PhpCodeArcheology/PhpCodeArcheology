<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Enums\BetterDirection;
use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;

class MetricType
{
    private function __construct(
        private readonly string $key,
        private readonly string $name,
        private readonly string $shortName,
        private readonly ?string $description,
        private MetricValueType $valueType,
        private readonly BetterDirection $better,
        private readonly MetricVisibility|array $visibility,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getValueType(): MetricValueType
    {
        return $this->valueType;
    }

    public static function fromArray(array $data): MetricType
    {
        [
            'key' => $key,
            'name' => $name,
            'shortName' => $shortName,
            'description' => $description,
            'valueType' => $valueType,
            'better' => $better,
            'visibility' => $visibility,
        ] = $data;

        return new MetricType(
            key: $key,
            name: $name,
            shortName: $shortName,
            description: $description,
            valueType: $valueType,
            better: $better,
            visibility: $visibility,
        );
    }

    public static function fromKey($key): MetricType
    {
        return new MetricType(
            $key,
            '',
            '',
            '',
            MetricValueType::Float,
            BetterDirection::High,
            MetricVisibility::ShowNowhere,
        );
    }

    public function getVisibility(): MetricVisibility|array
    {
        return $this->visibility;
    }

    public function __toArray(): array
    {
        $types = [
            MetricValueType::Int->value => 'int',
            MetricValueType::Float->value => 'float',
            MetricValueType::Array->value => 'string',
            MetricValueType::String->value => 'string',
            MetricValueType::Percentage->value => 'string',
            MetricValueType::Count->value => 'int',
        ];

        return [
            'key' => $this->key,
            'name' => $this->name,
            'shortName' => $this->shortName,
            'description' => $this->description,
            'valueType' => $types[$this->valueType->value] ?? 'mixed',
        ];
    }

    public function getBetter(): BetterDirection
    {
        return $this->better;
    }

    public function setValueType(MetricValueType $valueType): void
    {
        $this->valueType = $valueType;
    }
}
