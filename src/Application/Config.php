<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\Service\FrameworkDetectionResult;

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

    public function isQuickMode(): bool
    {
        return (bool) ($this->config['quickMode'] ?? false);
    }

    public function getReportDir(): string
    {
        return is_string($this->config['reportDir'] ?? null) ? $this->config['reportDir'] : '';
    }

    public function getRunningDir(): string
    {
        return is_string($this->config['runningDir'] ?? null) ? $this->config['runningDir'] : (getcwd() ?: '');
    }

    /** @return array<mixed> */
    public function getFiles(): array
    {
        return is_array($this->config['files'] ?? null) ? $this->config['files'] : [];
    }

    public function getReportTypes(): string
    {
        $rt = $this->config['reportType'] ?? 'html';

        return is_string($rt) ? $rt : 'html';
    }

    public function getMemoryLimit(): string
    {
        return is_string($this->config['memoryLimit'] ?? null) ? $this->config['memoryLimit'] : '1G';
    }

    public function isNoColor(): bool
    {
        return (bool) ($this->config['noColor'] ?? false);
    }

    public function getCommand(): ?string
    {
        $cmd = $this->config['command'] ?? null;

        return is_string($cmd) ? $cmd : null;
    }

    public function getFrameworkDetection(): ?FrameworkDetectionResult
    {
        $fd = $this->config['frameworkDetection'] ?? null;

        return $fd instanceof FrameworkDetectionResult ? $fd : null;
    }

    public function getFailOn(): ?string
    {
        $fo = $this->config['failOn'] ?? null;

        return is_string($fo) ? $fo : null;
    }

    public function getPackageSize(): int
    {
        return is_int($this->config['packageSize'] ?? null) ? $this->config['packageSize'] : 2;
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
