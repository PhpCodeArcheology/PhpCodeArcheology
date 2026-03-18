<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class CodeDuplicationCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private const MIN_TOKENS = 50;

    /** @var array<string, string> fileIdentifier → filePath */
    private array $filePaths = [];

    /** @var array<string, array> fileIdentifier → [hash => [startLine, ...]] */
    private array $fileHashes = [];

    private int $totalDuplicatedLines = 0;
    private int $totalLines = 0;

    public function beforeTraverse(): void
    {
        $this->filePaths = [];
        $this->fileHashes = [];
        $this->totalDuplicatedLines = 0;
        $this->totalLines = 0;
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof FileMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();
        $filePath = $metrics->get('filePath')?->getValue() ?? null;

        if ($filePath === null || !file_exists($filePath)) {
            return;
        }

        $this->filePaths[$identifierString] = $filePath;

        // Tokenize and normalize
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $tokens = @token_get_all($code);
        if ($tokens === false) {
            return;
        }

        $normalized = $this->normalizeTokens($tokens);
        if (count($normalized) < self::MIN_TOKENS) {
            return;
        }

        // Rolling hash: windows of MIN_TOKENS normalized tokens
        $hashes = [];
        for ($i = 0; $i <= count($normalized) - self::MIN_TOKENS; $i++) {
            $window = array_slice($normalized, $i, self::MIN_TOKENS);
            $hash = $this->hashWindow($window);
            if (!isset($hashes[$hash])) {
                $hashes[$hash] = [];
            }
            $hashes[$hash][] = $window[0]['line'];
        }

        $this->fileHashes[$identifierString] = $hashes;
    }

    public function afterTraverse(): void
    {
        // Collect all hashes across all files
        $globalHashes = [];
        foreach ($this->fileHashes as $fileId => $hashes) {
            foreach ($hashes as $hash => $lines) {
                if (!isset($globalHashes[$hash])) {
                    $globalHashes[$hash] = [];
                }
                $globalHashes[$hash][] = $fileId;
            }
        }

        // Find duplicates: hashes that appear in 2+ files or 2+ locations
        $duplicateHashes = array_filter($globalHashes, fn($files) => count($files) > 1);

        // Calculate duplication per file
        foreach ($this->filePaths as $fileId => $filePath) {
            $duplicatedLines = 0;
            $fileHashSet = $this->fileHashes[$fileId] ?? [];

            foreach ($fileHashSet as $hash => $lines) {
                if (isset($duplicateHashes[$hash])) {
                    $duplicatedLines += count($lines);
                }
            }

            // Avoid counting same lines multiple times
            $duplicatedLines = min($duplicatedLines, $this->getFileLoc($fileId));
            $loc = $this->getFileLoc($fileId);
            $rate = $loc > 0 ? round(($duplicatedLines / $loc) * 100, 2) : 0.0;

            $this->metricsController->setMetricValuesByIdentifierString(
                $fileId,
                [
                    'duplicationRate' => $rate,
                    'duplicatedLines' => $duplicatedLines,
                ]
            );

            $this->totalDuplicatedLines += $duplicatedLines;
            $this->totalLines += $loc;
        }

        // Set defaults for files without enough tokens
        foreach ($this->filePaths as $fileId => $filePath) {
            if (!isset($this->fileHashes[$fileId])) {
                $this->metricsController->setMetricValuesByIdentifierString(
                    $fileId,
                    ['duplicationRate' => 0.0, 'duplicatedLines' => 0]
                );
            }
        }

        // Project level
        $overallRate = $this->totalLines > 0
            ? round(($this->totalDuplicatedLines / $this->totalLines) * 100, 2)
            : 0.0;

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallDuplicationRate' => $overallRate,
                'overallDuplicatedLines' => $this->totalDuplicatedLines,
            ]
        );
    }

    private function normalizeTokens(array $tokens): array
    {
        $normalized = [];
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $normalized[] = ['type' => $token, 'line' => 0];
                continue;
            }

            [$id, $text, $line] = $token;

            // Skip whitespace, comments, doc comments
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG])) {
                continue;
            }

            // Normalize: replace variable names and string literals
            $type = match ($id) {
                T_VARIABLE => '$V',
                T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE => '"S"',
                T_LNUMBER, T_DNUMBER => '0',
                T_STRING => $text, // Keep function/class names
                default => token_name($id),
            };

            $normalized[] = ['type' => $type, 'line' => $line];
        }

        return $normalized;
    }

    private function hashWindow(array $window): string
    {
        $str = implode('|', array_column($window, 'type'));
        return md5($str);
    }

    private function getFileLoc(string $fileId): int
    {
        return $this->metricsController->getMetricValueByIdentifierString($fileId, 'loc')?->getValue() ?? 0;
    }
}
