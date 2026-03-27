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
    public function setDeltas(MetricsController $metricsController, Config $config): false|\DateTimeImmutable
    {
        $reportDirRaw = $config->get('reportDir');
        $outputDir = (is_string($reportDirRaw) ? $reportDirRaw : '').DIRECTORY_SEPARATOR;

        // Support both JSONL (new) and JSON (legacy)
        $historyFile = $outputDir.'history.jsonl';
        $isJsonl = true;
        if (!file_exists($historyFile)) {
            $historyFile = $outputDir.'history.json';
            $isJsonl = false;
            if (!file_exists($historyFile)) {
                return false;
            }
        }

        $historyValueTypes = [
            MetricValueType::Int,
            MetricValueType::Float,
            MetricValueType::Percentage,
        ];

        // Read last entry (last line for JSONL, whole file for JSON)
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($historyFile);
            if (null === $lastLine) {
                return false;
            }
            $historyFileData = json_decode($lastLine, true);
        } else {
            $rawData = @file_get_contents($historyFile);
            if (false === $rawData) {
                return false;
            }
            $historyFileData = json_decode($rawData, true);
            unset($rawData);
        }

        if (!is_array($historyFileData) || !isset($historyFileData['date'])) {
            return false;
        }

        $dateRaw = $historyFileData['date'];
        $historyDate = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', is_string($dateRaw) ? $dateRaw : '');
        unset($historyFileData);

        foreach ($this->getHistoryDataFromFile($historyFile, $isJsonl) as $historyData) {
            foreach ($historyData['data'] as $key => $historyValue) {
                $metricValue = $metricsController->getMetricValueByIdentifierString(
                    $historyData['key'],
                    (string) $key
                );

                if (!$metricValue instanceof MetricValue) {
                    continue;
                }

                $metricType = $metricValue->getMetricType();
                $valueType = $metricType->getValueType();

                if (MetricVisibility::ShowNowhere === $metricType->getVisibility() || MetricValueType::Storage === $metricType->getValueType()) {
                    continue;
                }

                $containsColon = is_string($metricValue->getValue()) && str_contains($metricValue->getValue(), ': ');
                $skip = !in_array($valueType, $historyValueTypes);
                $skip = $skip && !$containsColon;

                if ($skip) {
                    continue;
                }

                $better = $metricType->getBetter();

                $historyValue ??= 0;

                $deltaObject = new class {
                    public int|float $delta = 0;
                    public string $direction = '';
                    public ?bool $isBetter = null;
                };

                $currentValue = $metricValue->getValue();
                if ($containsColon && is_string($currentValue) && (is_string($historyValue) || is_numeric($historyValue))) {
                    $currentValue = (int) explode(': ', $currentValue)[1];
                    $historyValue = (int) explode(': ', is_string($historyValue) ? $historyValue : (string) $historyValue)[1];
                }

                $currentNum = is_numeric($currentValue) ? $currentValue + 0 : 0;
                $historyNum = is_numeric($historyValue) ? $historyValue + 0 : 0;
                $delta = $currentNum - $historyNum;

                $direction = 'sideways';
                $isBetter = null;
                switch (true) {
                    case BetterDirection::Low === $better && $delta < 0:
                        $direction = 'down';
                        $isBetter = true;
                        break;

                    case BetterDirection::Low === $better && $delta > 0:
                        $direction = 'up';
                        $isBetter = false;
                        break;

                    case BetterDirection::High === $better && $delta > 0:
                        $direction = 'up';
                        $isBetter = true;
                        break;

                    case BetterDirection::High === $better && $delta < 0:
                        $direction = 'down';
                        $isBetter = false;
                        break;
                }

                $deltaObject->delta = $delta;
                $deltaObject->isBetter = $isBetter;
                $deltaObject->direction = $direction;

                $metricValue->setDelta($deltaObject);
            }
        }

        return $historyDate;
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

                    return;
                }
            }
        }

        // Append current run as new line
        file_put_contents($historyFile, json_encode($metricHistory)."\n", FILE_APPEND);
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
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $lines || [] === $lines) {
            return null;
        }

        return end($lines);
    }
}
