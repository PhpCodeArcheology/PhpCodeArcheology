<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Data;

class ReportDataContainer
{
    /**
     * @var ReportDataCollection[]
     */
    private array $collections = [];

    /**
     * @param string $key
     * @return ReportDataCollection
     */
    public function get(string $key): ReportDataCollection
    {
        return $this->collections[$key];
    }

    /**
     * @param string $key
     * @param ReportDataCollection $value
     * @return void
     */
    public function set(string $key, ReportDataCollection $value): void
    {
        $this->collections[$key] = $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->collections[$key]);
    }

    /**
     * @return ReportDataCollection[]
     */
    public function getAll(): array
    {
        return $this->collections;
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->collections);
    }
}
