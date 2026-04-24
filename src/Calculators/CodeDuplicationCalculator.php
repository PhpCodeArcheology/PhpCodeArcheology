<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class CodeDuplicationCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    private const MIN_TOKENS = 50;

    /** @var array<string, string> fileIdentifier → filePath */
    private array $filePaths = [];

    /** @var array<string, array<string, list<int>>> fileIdentifier → [hash → [startLines]] */
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
        $filePath = $metrics->getString(MetricKey::FILE_PATH);

        if (!$filePath || !file_exists($filePath)) {
            return;
        }

        $this->filePaths[$identifierString] = $filePath;

        // Tokenize and normalize
        $code = @file_get_contents($filePath);
        if (false === $code) {
            return;
        }

        $tokens = @token_get_all($code);

        $normalized = $this->normalizeTokens($tokens);
        if (count($normalized) < self::MIN_TOKENS) {
            return;
        }

        // Rolling hash: windows of MIN_TOKENS normalized tokens
        $hashes = [];
        for ($i = 0; $i <= count($normalized) - self::MIN_TOKENS; ++$i) {
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
        $duplicateHashes = array_filter($globalHashes, fn ($files): bool => count($files) > 1);

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

            $this->writer->setMetricValuesByIdentifierString(
                $fileId,
                [
                    MetricKey::DUPLICATION_RATE => $rate,
                    MetricKey::DUPLICATED_LINES => $duplicatedLines,
                ]
            );

            $this->totalDuplicatedLines += $duplicatedLines;
            $this->totalLines += $loc;
        }

        // Set defaults for files without enough tokens
        foreach (array_keys($this->filePaths) as $fileId) {
            if (!isset($this->fileHashes[$fileId])) {
                $this->writer->setMetricValuesByIdentifierString(
                    $fileId,
                    [MetricKey::DUPLICATION_RATE => 0.0, MetricKey::DUPLICATED_LINES => 0]
                );
            }
        }

        // Project level
        $overallRate = $this->totalLines > 0
            ? round(($this->totalDuplicatedLines / $this->totalLines) * 100, 2)
            : 0.0;

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_DUPLICATION_RATE => $overallRate,
                MetricKey::OVERALL_DUPLICATED_LINES => $this->totalDuplicatedLines,
            ]
        );
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<array{type: string, line: int}>
     */
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

    /**
     * @param list<array{type: string, line: int}> $window
     */
    private function hashWindow(array $window): string
    {
        $str = implode('|', array_column($window, 'type'));

        return md5($str);
    }

    private function getFileLoc(string $fileId): int
    {
        return $this->reader->getMetricValueByIdentifierString($fileId, MetricKey::LOC)?->asInt() ?? 0;
    }
}
