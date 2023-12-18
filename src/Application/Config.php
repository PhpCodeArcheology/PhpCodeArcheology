<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

class Config
{
    private array $config = [];

    public function get(string $key): mixed
    {
        return $this->has($key) ? $this->config[$key] : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * @throws ConfigException
     */
    public function validate(): void
    {
        if (! $this->has('files') || empty($this->config['files'])) {
            throw new ConfigException('No files or directories to analyze.');
        }

        foreach ($this->get('files') as $file) {
            if (! file_exists($file)) {
                throw new ConfigException("File $file does not exist.");
            }
        }
    }
}