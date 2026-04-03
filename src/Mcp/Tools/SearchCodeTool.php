<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class SearchCodeTool
{
    public function __construct(
        private readonly MetricsReaderInterface $metricsController,
    ) {
    }

    public function searchCode(string $query, string $entity_type = 'any', int $limit = 20): string
    {
        try {
            if ('' === trim($query)) {
                return 'Error: query must not be empty.';
            }

            $results = [];

            foreach ($this->metricsController->getAllCollections() as $collection) {
                $type = match (true) {
                    $collection instanceof ClassMetricsCollection => 'class',
                    $collection instanceof FileMetricsCollection => 'file',
                    $collection instanceof FunctionMetricsCollection => 'function',
                    default => null,
                };

                if (null === $type) {
                    continue;
                }

                if ('any' !== $entity_type && $type !== $entity_type) {
                    continue;
                }

                $name = $collection->getName();
                $singleName = $collection->getString(MetricKey::SINGLE_NAME);

                if (false === stripos((string) $name, $query) && false === stripos($singleName, $query)) {
                    continue;
                }

                $displayName = '' !== $singleName ? $singleName : basename((string) $name);

                $results[] = [
                    'type' => $type,
                    'name' => $displayName,
                    'fullName' => $name,
                    'cc' => $collection->getInt(MetricKey::CC),
                    'lloc' => $collection->getInt(MetricKey::LLOC),
                    'mi' => $collection->getFloat(MetricKey::MAINTAINABILITY_INDEX),
                    'priority' => $collection->getFloat(MetricKey::REFACTORING_PRIORITY),
                ];
            }

            if ([] === $results) {
                $typeHint = 'any' !== $entity_type ? " (type: {$entity_type})" : '';

                return "No results found for '{$query}'{$typeHint}.";
            }

            $total = count($results);
            $results = array_slice($results, 0, max(1, $limit));

            $lines = [
                "# Search Results: '{$query}'",
                'Total: '.$total.' | Showing: '.count($results),
                '',
                sprintf('%-10s %-40s %4s %6s %5s %8s', 'Type', 'Name', 'CC', 'LLOC', 'MI', 'Priority'),
                str_repeat('-', 74),
            ];

            foreach ($results as $r) {
                $shortName = strlen((string) $r['name']) > 38 ? '...'.substr((string) $r['name'], -35) : $r['name'];
                $lines[] = sprintf('%-10s %-40s %4s %6s %5s %8s',
                    $r['type'],
                    $shortName,
                    $r['cc'] ?: '-',
                    $r['lloc'] ?: '-',
                    $r['mi'] ? round((float) $r['mi'], 1) : '-',
                    $r['priority'] ?: '-'
                );
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while searching code.';
        }
    }
}
