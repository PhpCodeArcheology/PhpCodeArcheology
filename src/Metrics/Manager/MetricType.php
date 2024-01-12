<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Manager;

class MetricType
{
    const VALUE_INT = 0;
    const VALUE_FLOAT = 1;
    const VALUE_STRING = 2;
    const VALUE_ARRAY = 3;
    const VALUE_PERCENTAGE = 4;
    const VALUE_COUNT = 5;

    const SHOW_IN_DETAILS = 0;
    const SHOW_IN_LIST = 1;
    const SHOW_EVERYWHERE = 2;
    const SHOW_NOWHERE = 3;

    /**
     * @param string $key
     * @param string $name
     * @param string $shortName
     * @param string|null $description
     * @param int $valueType
     */
    private function __construct(
        private readonly string $key,
        private readonly string $name,
        private readonly string $shortName,
        private readonly ?string $description,
        private readonly int $valueType,
        private readonly int $visibility
    )
    {
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getShortName(): string
    {
        return $this->shortName;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getValueType(): int
    {
        return $this->valueType;
    }

    /**
     * @param array $data
     * @return MetricType
     */
    public static function fromArray(array $data): MetricType
    {
        [
            'key' => $key,
            'name' => $name,
            'shortName' => $shortName,
            'description' => $description,
            'valueType' => $valueType,
            'visibility' => $visibility
        ] = $data;

        return new MetricType($key, $name, $shortName, $description, $valueType, $visibility);
    }

    /**
     * @return int
     */
    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function __toArray(): array
    {
        $types = [
            self::VALUE_INT => 'int',
            self::VALUE_FLOAT => 'float',
            self::VALUE_ARRAY => 'string',
            self::VALUE_STRING => 'string',
            self::VALUE_PERCENTAGE => 'string',
            self::VALUE_COUNT => 'int',
        ];

        return [
            'key' => $this->key,
            'name' => $this->name,
            'shortName' => $this->shortName,
            'description' => $this->description,
            'valueType' => $types[$this->valueType],
        ];
    }
}
