<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

final class Config implements AnalysisConfigInterface
{
    private const VALID_REPORT_TYPES = ['html', 'markdown', 'json', 'sarif', 'ai-summary', 'graph'];

    /** @var array<string, mixed> */
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

        if ([] !== $errors) {
            $message = "Configuration errors:\n";
            foreach ($errors as $i => $error) {
                $message .= '  '.($i + 1).'. '.$error."\n";
            }
            throw new ConfigException($message);
        }
    }

    /** @return string[] */
    private function collectValidationErrors(): array
    {
        $errors = [];

        if (!$this->has('files') || empty($this->config['files'])) {
            $errors[] = 'No files or directories to analyze.';

            return $errors;
        }

        $files = $this->get('files');
        foreach (is_array($files) ? $files : [] as $file) {
            if (!is_string($file)) {
                continue;
            }
            if (!file_exists($file)) {
                $suggestion = $this->suggestAlternative($file);
                $msg = "Path '$file' does not exist.";
                if (null !== $suggestion) {
                    $msg .= " Did you mean '$suggestion'?";
                }
                $errors[] = $msg;
            }
        }

        if ($this->has('reportType')) {
            $rawReportTypes = $this->get('reportType');
            $reportTypes = is_array($rawReportTypes) ? $rawReportTypes : [$rawReportTypes];
            foreach ($reportTypes as $reportType) {
                if (!is_string($reportType)) {
                    continue;
                }
                $reportType = strtolower($reportType);
                if (!in_array($reportType, self::VALID_REPORT_TYPES, true)) {
                    $errors[] = "Unknown report type '$reportType'. Valid types: ".implode(', ', self::VALID_REPORT_TYPES).'.';
                }
            }
        }

        if ($this->has('packageSize')) {
            $packageSize = $this->get('packageSize');
            if (!is_int($packageSize) || $packageSize < 1) {
                $displayValue = is_scalar($packageSize) ? (string) $packageSize : gettype($packageSize);
                $errors[] = "packageSize must be a positive integer, got '$displayValue'.";
            }
        }

        return $errors;
    }

    private function suggestAlternative(string $path): ?string
    {
        $dir = dirname($path);
        $base = basename($path);

        if ('.' === $dir || '' === $dir) {
            $dir = getcwd() ?: '';
        }

        if ('' === $dir || !is_dir($dir)) {
            return null;
        }

        $candidates = @scandir($dir);
        if (false === $candidates) {
            return null;
        }

        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if ('.' === $candidate || '..' === $candidate) {
                continue;
            }

            if (!is_dir($dir.DIRECTORY_SEPARATOR.$candidate)) {
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
