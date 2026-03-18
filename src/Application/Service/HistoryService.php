<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\Enums\BetterDirection;
use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class HistoryService
{
    public function setDeltas(MetricsController $metricsController, Config $config): false|\DateTimeImmutable
    {
        $outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;

        // Support both JSONL (new) and JSON (legacy)
        $historyFile = $outputDir . 'history.jsonl';
        $isJsonl = true;
        if (!file_exists($historyFile)) {
            $historyFile = $outputDir . 'history.json';
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
            if ($lastLine === null) {
                return false;
            }
            $historyFileData = json_decode($lastLine);
        } else {
            $rawData = @file_get_contents($historyFile);
            if ($rawData === false) {
                return false;
            }
            $historyFileData = json_decode($rawData);
            unset($rawData);
        }

        if ($historyFileData === null || !isset($historyFileData->date)) {
            return false;
        }

        $historyDate = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', $historyFileData->date);
        unset($historyFileData);

        foreach ($this->getHistoryDataFromFile($historyFile, $isJsonl) as $historyData) {
            foreach ($historyData['data'] as $key => $historyValue) {
                $metricValue = $metricsController->getMetricValueByIdentifierString(
                    $historyData['key'],
                    $key
                );

                if (!$metricValue) {
                    continue;
                }

                $metricType = $metricValue->getMetricType();
                $valueType = $metricType->getValueType();

                if ($metricType->getVisibility() === MetricVisibility::ShowNowhere || $metricType->getValueType() === MetricValueType::Storage) {
                    continue;
                }

                $containsColon = is_string($metricValue->getValue()) && str_contains($metricValue->getValue(), ': ');
                $skip = !in_array($valueType, $historyValueTypes);
                $skip = $skip && !$containsColon;

                if ($skip) {
                    continue;
                }

                $better = $metricType->getBetter();

                $historyValue = $historyValue ?? 0;

                $deltaObject = new class {
                    public int|float $delta = 0;
                    public string $direction = '';
                    public null|bool $isBetter = null;
                };

                $currentValue = $metricValue->getValue();
                if ($containsColon) {
                    $currentValue = (int) explode(': ', $currentValue)[1];
                    $historyValue = (int) explode(': ', $historyValue)[1];
                }

                $delta = $currentValue - $historyValue;

                $direction = 'sideways';
                $isBetter = null;
                switch (true) {
                    case $better === BetterDirection::Low && $delta < 0:
                        $direction = 'down';
                        $isBetter = true;
                        break;

                    case $better === BetterDirection::Low && $delta > 0:
                        $direction = 'up';
                        $isBetter = false;
                        break;

                    case $better === BetterDirection::High && $delta > 0:
                        $direction = 'up';
                        $isBetter = true;
                        break;

                    case $better === BetterDirection::High && $delta < 0:
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
        $outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;
        $historyFile = $outputDir . 'history.jsonl';

        $metricHistory = [
            'date' => (new \DateTimeImmutable())->format('Y-m-d-H-i-s'),
            'data' => [],
        ];

        foreach ($this->getHistoryData($metricsController) as $historyData) {
            if (!isset($metricHistory['data'][$historyData['collectionKey']])) {
                $metricHistory['data'][$historyData['collectionKey']] = [];
            }
            $metricHistory['data'][$historyData['collectionKey']][$historyData['key']] = $historyData['value'];
        }

        // Migrate old history.json → first line of history.jsonl
        $oldHistoryFile = $outputDir . 'history.json';
        if (file_exists($oldHistoryFile) && !file_exists($historyFile)) {
            $oldData = @file_get_contents($oldHistoryFile);
            if ($oldData !== false) {
                file_put_contents($historyFile, trim($oldData) . "\n");
                @unlink($oldHistoryFile);
            }
        }

        // Skip writing if data hasn't changed since last run
        $currentDataHash = md5(json_encode($metricHistory['data']));
        $lastLine = $this->getLastLineOfFile($historyFile);
        if ($lastLine !== null) {
            $lastEntry = json_decode($lastLine, true);
            if ($lastEntry !== null && isset($lastEntry['data'])) {
                $lastDataHash = md5(json_encode($lastEntry['data']));
                if ($currentDataHash === $lastDataHash) {
                    // Data unchanged — update timestamp of last entry instead of appending
                    $lastEntry['date'] = $metricHistory['date'];
                    $lines = @file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    $lines[count($lines) - 1] = json_encode($lastEntry);
                    file_put_contents($historyFile, implode("\n", $lines) . "\n");
                    return;
                }
            }
        }

        // Append current run as new line
        file_put_contents($historyFile, json_encode($metricHistory) . "\n", FILE_APPEND);
    }

    private function getHistoryData(MetricsController $metricsController): \Generator
    {
        foreach ($metricsController->getAllCollections() as $metricCollectionKey => $metricCollection) {
            foreach ($this->getMetricValues($metricCollection) as $metricValue) {
                if ($metricValue->getMetricType()->getVisibility() === MetricVisibility::ShowNowhere) {
                    continue;
                }

                yield [
                    'collectionKey' => $metricCollectionKey,
                    'key' => $metricValue->getMetricTypeKey(),
                    'value' => $metricValue->getValue(),
                ];
            }
        }
    }

    private function getMetricValues(MetricsCollectionInterface $metricCollection): \Generator
    {
        foreach ($metricCollection->getAll() as $metricValue) {
            yield $metricValue;
        }
    }

    private function getHistoryDataFromFile(string $file, bool $isJsonl = false): \Generator
    {
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($file);
            if ($lastLine === null) {
                return;
            }
            $history = json_decode($lastLine);
        } else {
            $jsonData = @file_get_contents($file);
            if ($jsonData === false) {
                return;
            }
            $history = json_decode($jsonData);
        }

        if ($history === null || !isset($history->data)) {
            return;
        }

        foreach ($history->data as $key => $historyData) {
            yield [
                'key' => $key,
                'data' => $historyData,
            ];
        }
    }

    private function getLastLineOfFile(string $file): ?string
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            return null;
        }
        return end($lines);
    }
}
