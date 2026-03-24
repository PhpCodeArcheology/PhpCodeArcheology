<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class MetricsTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
        private readonly MetricsController $metricsController
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

                if ($entityType === null) {
                    continue;
                }

                if ($type !== 'any' && $entityType !== $type) {
                    continue;
                }

                $name = $collection->getName();
                $singleName = $collection->get('singleName')?->getValue() ?? '';

                if (stripos($name, $entity) === false && stripos($singleName, $entity) === false) {
                    continue;
                }

                $found[] = [$entityType, $collection];
            }

            if (empty($found)) {
                $typeHint = $type !== 'any' ? " (type: {$type})" : '';
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
                        $lines[] = sprintf('%-35s [array, %d items]', $key . ':', $count);
                    } elseif (is_bool($value)) {
                        $lines[] = sprintf('%-35s %s', $key . ':', $value ? 'true' : 'false');
                    } elseif (is_float($value)) {
                        $lines[] = sprintf('%-35s %s', $key . ':', round($value, 4));
                    } else {
                        $lines[] = sprintf('%-35s %s', $key . ':', $value ?? 'null');
                    }
                }

                $lines[] = '';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'Error retrieving metrics: ' . $e->getMessage();
        }
    }
}
