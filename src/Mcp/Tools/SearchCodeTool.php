<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class SearchCodeTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
        private readonly MetricsController $metricsController
    ) {
    }

    public function searchCode(string $query, string $entity_type = 'any', int $limit = 20): string
    {
        try {
            if (trim($query) === '') {
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

                if ($type === null) {
                    continue;
                }

                if ($entity_type !== 'any' && $type !== $entity_type) {
                    continue;
                }

                $name = $collection->getName();
                $singleName = $collection->get('singleName')?->getValue() ?? '';

                if (stripos($name, $query) === false && stripos($singleName, $query) === false) {
                    continue;
                }

                $displayName = $singleName !== '' ? $singleName : basename($name);

                $results[] = [
                    'type' => $type,
                    'name' => $displayName,
                    'fullName' => $name,
                    'cc' => $collection->get('cc')?->getValue() ?? 0,
                    'lloc' => $collection->get('lloc')?->getValue() ?? 0,
                    'mi' => $collection->get('maintainabilityIndex')?->getValue() ?? 0,
                    'priority' => $collection->get('refactoringPriority')?->getValue() ?? 0,
                ];
            }

            if (empty($results)) {
                $typeHint = $entity_type !== 'any' ? " (type: {$entity_type})" : '';
                return "No results found for '{$query}'{$typeHint}.";
            }

            $total = count($results);
            $results = array_slice($results, 0, max(1, $limit));

            $lines = [
                "# Search Results: '{$query}'",
                'Total: ' . $total . ' | Showing: ' . count($results),
                '',
                sprintf('%-10s %-40s %4s %6s %5s %8s', 'Type', 'Name', 'CC', 'LLOC', 'MI', 'Priority'),
                str_repeat('-', 74),
            ];

            foreach ($results as $r) {
                $shortName = strlen($r['name']) > 38 ? '...' . substr($r['name'], -35) : $r['name'];
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
            return 'Error searching code: ' . $e->getMessage();
        }
    }
}
