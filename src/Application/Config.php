<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

final class Config
{
    private const VALID_REPORT_TYPES = ['html', 'markdown', 'json', 'sarif', 'ai-summary', 'graph'];

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
        $errors = $this->collectValidationErrors();

        if (!empty($errors)) {
            $message = "Configuration errors:\n";
            foreach ($errors as $i => $error) {
                $message .= '  ' . ($i + 1) . '. ' . $error . "\n";
            }
            throw new ConfigException($message);
        }
    }

    private function collectValidationErrors(): array
    {
        $errors = [];

        if (!$this->has('files') || empty($this->config['files'])) {
            $errors[] = 'No files or directories to analyze.';
            return $errors;
        }

        foreach ($this->get('files') as $file) {
            if (!file_exists($file)) {
                $suggestion = $this->suggestAlternative($file);
                $msg = "Path '$file' does not exist.";
                if ($suggestion !== null) {
                    $msg .= " Did you mean '$suggestion'?";
                }
                $errors[] = $msg;
            }
        }

        if ($this->has('reportType')) {
            $reportTypes = $this->get('reportType');
            $reportTypes = is_array($reportTypes) ? $reportTypes : [$reportTypes];
            foreach ($reportTypes as $reportType) {
                $reportType = strtolower($reportType);
                if (!in_array($reportType, self::VALID_REPORT_TYPES, true)) {
                    $errors[] = "Unknown report type '$reportType'. Valid types: " . implode(', ', self::VALID_REPORT_TYPES) . '.';
                }
            }
        }

        if ($this->has('packageSize')) {
            $packageSize = $this->get('packageSize');
            if (!is_int($packageSize) || $packageSize < 1) {
                $errors[] = "packageSize must be a positive integer, got '$packageSize'.";
            }
        }

        return $errors;
    }

    private function suggestAlternative(string $path): ?string
    {
        $dir = dirname($path);
        $base = basename($path);

        if ($dir === '.' || $dir === '') {
            $dir = getcwd();
        }

        if (!is_dir($dir)) {
            return null;
        }

        $candidates = @scandir($dir);
        if ($candidates === false) {
            return null;
        }

        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if ($candidate === '.' || $candidate === '..') {
                continue;
            }

            if (!is_dir($dir . DIRECTORY_SEPARATOR . $candidate)) {
                continue;
            }

            $distance = levenshtein(strtolower($base), strtolower($candidate));
            if ($distance < $bestDistance && $distance <= 3) {
                $bestDistance = $distance;
                $bestMatch = $candidate;
            }
        }

        return $bestMatch;
    }
}
