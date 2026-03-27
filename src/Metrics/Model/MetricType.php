<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Enums\BetterDirection;
use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;

class MetricType
{
    /**
     * @param MetricVisibility|list<MetricVisibility> $visibility
     */
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

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): MetricType
    {
        $keyRaw = $data['key'] ?? null;
        $nameRaw = $data['name'] ?? null;
        $shortNameRaw = $data['shortName'] ?? null;
        $descriptionRaw = $data['description'] ?? null;
        $valueTypeRaw = $data['valueType'] ?? null;
        $betterRaw = $data['better'] ?? null;
        $rawVisibility = $data['visibility'] ?? null;

        if ($rawVisibility instanceof MetricVisibility) {
            $visibility = $rawVisibility;
        } elseif (is_array($rawVisibility)) {
            $visibility = array_values(array_filter(
                $rawVisibility,
                fn (mixed $v): bool => $v instanceof MetricVisibility
            ));
        } else {
            $visibility = MetricVisibility::ShowNowhere;
        }

        return new MetricType(
            key: is_string($keyRaw) ? $keyRaw : '',
            name: is_string($nameRaw) ? $nameRaw : '',
            shortName: is_string($shortNameRaw) ? $shortNameRaw : '',
            description: is_string($descriptionRaw) ? $descriptionRaw : null,
            valueType: $valueTypeRaw instanceof MetricValueType ? $valueTypeRaw : MetricValueType::Float,
            better: $betterRaw instanceof BetterDirection ? $betterRaw : BetterDirection::High,
            visibility: $visibility,
        );
    }

    public static function fromKey(string $key): MetricType
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

    /**
     * @return MetricVisibility|list<MetricVisibility>
     */
    public function getVisibility(): MetricVisibility|array
    {
        return $this->visibility;
    }

    /**
     * @return array{key: string, name: string, shortName: string, description: string|null, valueType: string}
     */
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
