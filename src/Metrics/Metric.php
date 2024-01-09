<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics;

class Metric
{
    private function __construct(
        private string $key,
        private string $label,
        private string $type
    )
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function ofKeyLabelAndType(string $key, $label, $type): Metric
    {
        return new Metric($key, $label, $type);
    }
}
