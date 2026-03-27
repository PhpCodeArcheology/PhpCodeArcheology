<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class MetricsTool
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    public function getMetrics(string $entity, string $type = 'any'): string
    {
        try {
            $found = [];

            foreach ($this->metricsController->getAllCollections() as $collection) {
                $entityType = match (true) {
                    $collection instanceof ClassMetricsCollection => 'class',
                    $collection instanceof FileMetricsCollection => 'file',
                    $collection instanceof FunctionMetricsCollection => 'function',
                    default => null,
                };

                if (null === $entityType) {
                    continue;
                }

                if ('any' !== $type && $entityType !== $type) {
                    continue;
                }

                $name = $collection->getName();
                $singleName = $collection->getString(MetricKey::SINGLE_NAME);

                if (false === stripos((string) $name, $entity) && false === stripos($singleName, $entity)) {
                    continue;
                }

                $found[] = [$entityType, $collection];
            }

            if ([] === $found) {
                $typeHint = 'any' !== $type ? " (type: {$type})" : '';

                return "No entity found matching '{$entity}'{$typeHint}.";
            }

            $lines = [];

            foreach ($found as [$entityType, $collection]) {
                $name = $collection->getName();
                $lines[] = "# Metrics: {$name} ({$entityType})";
                $lines[] = '';

                $allMetrics = $collection->getAll();
                if (empty($allMetrics)) {
                    $lines[] = '(no metrics available)';
                    $lines[] = '';
                    continue;
                }

                foreach ($allMetrics as $key => $metricValue) {
                    $value = $metricValue->getValue();
                    if (is_array($value)) {
                        $count = count($value);
                        $lines[] = sprintf('%-35s [array, %d items]', $key.':', $count);
                    } elseif (is_bool($value)) {
                        $lines[] = sprintf('%-35s %s', $key.':', $value ? 'true' : 'false');
                    } elseif (is_float($value)) {
                        $lines[] = sprintf('%-35s %s', $key.':', round($value, 4));
                    } elseif (is_int($value)) {
                        $lines[] = sprintf('%-35s %d', $key.':', $value);
                    } elseif (is_string($value)) {
                        $lines[] = sprintf('%-35s %s', $key.':', $value);
                    } else {
                        $lines[] = sprintf('%-35s %s', $key.':', 'null');
                    }
                }

                $lines[] = '';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving metrics.';
        }
    }
}
