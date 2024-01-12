<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Manager;

readonly class MetricCategory
{
    /**
     * @var string
     */
    private string $key;

    /**
     * @param string $name
     */
    private function __construct(private string $name)
    {
        $this->key = md5($name);
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
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $name
     * @return MetricCategory
     */
    public static function ofName(string $name): MetricCategory
    {
        return new MetricCategory($name);
    }
}
