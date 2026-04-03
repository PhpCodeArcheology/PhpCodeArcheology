<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\Enums\BetterDirection;
use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;

class HistoryService
{
    /** @var array<string, string> */
    private array $lastLineCache = [];

    public function setDeltas(MetricsController $metricsController, Config $config): false|\DateTimeImmutable
    {
        $reportDirRaw = $config->get('reportDir');
        $outputDir = (is_string($reportDirRaw) ? $reportDirRaw : '').DIRECTORY_SEPARATOR;

        $fileInfo = $this->resolveHistoryFilePath($outputDir);
        if (false === $fileInfo) {
            return false;
        }
        $historyFile = $fileInfo['path'];
        $isJsonl = $fileInfo['isJsonl'];

        $historyFileData = $this->readLastHistoryFileData($historyFile, $isJsonl);
        if (!is_array($historyFileData) || !isset($historyFileData['date'])) {
            return false;
        }

        $dateRaw = $historyFileData['date'];
        $historyDate = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', is_string($dateRaw) ? $dateRaw : '');

        foreach ($this->getHistoryDataFromFile($historyFile, $isJsonl) as $historyData) {
            foreach ($historyData['data'] as $key => $historyValue) {
                $metricValue = $metricsController->getMetricValueByIdentifierString(
                    $historyData['key'],
                    (string) $key
                );

                if (!$metricValue instanceof MetricValue) {
                    continue;
                }

                $this->applyDeltaToMetric($metricValue, $historyValue);
            }
        }

        return $historyDate;
    }

    /**
     * @return array{path: string, isJsonl: bool}|false
     */
    private function resolveHistoryFilePath(string $outputDir): array|false
    {
        $historyFile = $outputDir.'history.jsonl';
        if (file_exists($historyFile)) {
            return ['path' => $historyFile, 'isJsonl' => true];
        }

        $historyFile = $outputDir.'history.json';
        if (!file_exists($historyFile)) {
            return false;
        }

        return ['path' => $historyFile, 'isJsonl' => false];
    }

    /**
     * @return array<mixed>|false
     */
    private function readLastHistoryFileData(string $historyFile, bool $isJsonl): array|false
    {
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($historyFile);
            if (null === $lastLine) {
                return false;
            }

            $decoded = json_decode($lastLine, true);

            return is_array($decoded) ? $decoded : false;
        }

        $rawData = @file_get_contents($historyFile);
        if (false === $rawData) {
            return false;
        }

        $decoded = json_decode($rawData, true);

        return is_array($decoded) ? $decoded : false;
    }

    private function applyDeltaToMetric(MetricValue $metricValue, mixed $historyValue): void
    {
        $metricType = $metricValue->getMetricType();
        $valueType = $metricType->getValueType();

        if (MetricVisibility::ShowNowhere === $metricType->getVisibility() || MetricValueType::Storage === $valueType) {
            return;
        }

        $historyValueTypes = [MetricValueType::Int, MetricValueType::Float, MetricValueType::Percentage];
        $containsColon = is_string($metricValue->getValue()) && str_contains($metricValue->getValue(), ': ');
        if (!in_array($valueType, $historyValueTypes) && !$containsColon) {
            return;
        }

        $better = $metricType->getBetter();
        $historyValue ??= 0;

        $values = $this->normalizeValuesForDelta($metricValue->getValue(), $historyValue, $containsColon);
        $delta = $values['current'] - $values['history'];

        $metricValue->setDelta($this->createDeltaObject($delta, $better));
    }

    /**
     * @return array{current: float|int, history: float|int}
     */
    private function normalizeValuesForDelta(mixed $currentValue, mixed $historyValue, bool $containsColon): array
    {
        if ($containsColon && is_string($currentValue) && (is_string($historyValue) || is_numeric($historyValue))) {
            $currentValue = (int) explode(': ', $currentValue)[1];
            $historyValue = (int) explode(': ', is_string($historyValue) ? $historyValue : (string) $historyValue)[1];
        }

        return [
            'current' => is_numeric($currentValue) ? $currentValue + 0 : 0,
            'history' => is_numeric($historyValue) ? $historyValue + 0 : 0,
        ];
    }

    private function createDeltaObject(float|int $delta, BetterDirection $better): object
    {
        [$direction, $isBetter] = $this->determineDeltaDirection($delta, $better);

        $deltaObject = new class {
            public int|float $delta = 0;
            public string $direction = '';
            public ?bool $isBetter = null;
        };
        $deltaObject->delta = $delta;
        $deltaObject->direction = $direction;
        $deltaObject->isBetter = $isBetter;

        return $deltaObject;
    }

    /**
     * @return array{string, ?bool}
     */
    private function determineDeltaDirection(float|int $delta, BetterDirection $better): array
    {
        if (BetterDirection::Low === $better && $delta < 0) {
            return ['down', true];
        }
        if (BetterDirection::Low === $better && $delta > 0) {
            return ['up', false];
        }
        if (BetterDirection::High === $better && $delta > 0) {
            return ['up', true];
        }
        if (BetterDirection::High === $better && $delta < 0) {
            return ['down', false];
        }

        return ['sideways', null];
    }

    public function writeHistory(MetricsController $metricsController, Config $config): void
    {
        $reportDirRaw = $config->get('reportDir');
        $outputDir = (is_string($reportDirRaw) ? $reportDirRaw : '').DIRECTORY_SEPARATOR;
        $historyFile = $outputDir.'history.jsonl';

        $metricHistory = [
            'date' => (new \DateTimeImmutable())->format('Y-m-d-H-i-s'),
            'data' => [],
        ];

        foreach ($this->getHistoryData($metricsController) as $historyData) {
            $collectionKey = $historyData['collectionKey'];
            // Compact format: only store project-level metrics to keep entries small.
            // Old (full) entries are still read correctly by the readers.
            if ('ProjectCollection' !== $collectionKey) {
                continue;
            }
            if (!isset($metricHistory['data'][$collectionKey])) {
                $metricHistory['data'][$collectionKey] = [];
            }
            $metricHistory['data'][$collectionKey][$historyData['key']] = $historyData['value'];
        }

        // Migrate old history.json → first line of history.jsonl
        $oldHistoryFile = $outputDir.'history.json';
        if (file_exists($oldHistoryFile) && !file_exists($historyFile)) {
            $oldData = @file_get_contents($oldHistoryFile);
            if (false !== $oldData) {
                file_put_contents($historyFile, trim($oldData)."\n");
                @unlink($oldHistoryFile);
            }
        }

        // Skip writing if data hasn't changed since last run
        $currentDataHash = md5(json_encode($metricHistory['data']) ?: '');
        $lastLine = $this->getLastLineOfFile($historyFile);
        if (null !== $lastLine) {
            $lastEntry = json_decode($lastLine, true);
            if (is_array($lastEntry) && isset($lastEntry['data'])) {
                $lastDataHash = md5(json_encode($lastEntry['data']) ?: '');
                if ($currentDataHash === $lastDataHash) {
                    // Data unchanged — update timestamp of last entry instead of appending
                    $lastEntry['date'] = $metricHistory['date'];
                    $lines = @file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    $lines[count($lines) - 1] = json_encode($lastEntry);
                    file_put_contents($historyFile, implode("\n", $lines)."\n");
                    unset($this->lastLineCache[$historyFile]);

                    return;
                }
            }
        }

        // Append current run as new line
        file_put_contents($historyFile, json_encode($metricHistory)."\n", FILE_APPEND);
        unset($this->lastLineCache[$historyFile]);
    }

    /**
     * @return \Generator<int, array{collectionKey: string, key: string, value: mixed}>
     */
    private function getHistoryData(MetricsController $metricsController): \Generator
    {
        foreach ($metricsController->getAllCollections() as $metricCollectionKey => $metricCollection) {
            foreach ($this->getMetricValues($metricCollection) as $metricValue) {
                if (MetricVisibility::ShowNowhere === $metricValue->getMetricType()->getVisibility()) {
                    continue;
                }

                yield [
                    'collectionKey' => (string) $metricCollectionKey,
                    'key' => $metricValue->getMetricTypeKey(),
                    'value' => $metricValue->getValue(),
                ];
            }
        }
    }

    /**
     * @return \Generator<int, MetricValue>
     */
    private function getMetricValues(MetricsCollectionInterface $metricCollection): \Generator
    {
        foreach ($metricCollection->getAll() as $metricValue) {
            yield $metricValue;
        }
    }

    /**
     * @return \Generator<int, array{key: string, data: array<array-key, mixed>}>
     */
    private function getHistoryDataFromFile(string $file, bool $isJsonl = false): \Generator
    {
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($file);
            if (null === $lastLine) {
                return;
            }
            $history = json_decode($lastLine, true);
        } else {
            $jsonData = @file_get_contents($file);
            if (false === $jsonData) {
                return;
            }
            $history = json_decode($jsonData, true);
        }

        if (!is_array($history) || !isset($history['data']) || !is_array($history['data'])) {
            return;
        }

        foreach ($history['data'] as $key => $historyData) {
            if (!is_array($historyData)) {
                continue;
            }
            yield [
                'key' => (string) $key,
                'data' => $historyData,
            ];
        }
    }

    private function getLastLineOfFile(string $file): ?string
    {
        if (isset($this->lastLineCache[$file])) {
            return $this->lastLineCache[$file];
        }

        $fp = @fopen($file, 'rb');
        if (false === $fp) {
            return null;
        }

        try {
            fseek($fp, 0, SEEK_END);
            $size = ftell($fp);

            if (false === $size || 0 === $size) {
                return null;
            }

            $buffer = '';
            $pos = $size;

            while ($pos > 0) {
                $readSize = min(8192, $pos);
                $pos -= $readSize;
                fseek($fp, $pos);
                $chunk = fread($fp, $readSize);
                if (false === $chunk) {
                    return null;
                }
                $buffer = $chunk.$buffer;

                $trimmed = rtrim($buffer, "\n\r");
                $nlPos = strrpos($trimmed, "\n");
                if (false !== $nlPos) {
                    $result = substr($trimmed, $nlPos + 1);
                    $this->lastLineCache[$file] = $result;

                    return $result;
                }
            }

            $trimmed = rtrim($buffer, "\n\r");
            if ('' === $trimmed) {
                return null;
            }

            $this->lastLineCache[$file] = $trimmed;

            return $trimmed;
        } finally {
            fclose($fp);
        }
    }
}
