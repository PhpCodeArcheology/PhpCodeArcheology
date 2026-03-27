<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class ClassListTool
{
    private const SORT_KEYS = [
        'name' => 'name',
        'cc' => 'cc',
        'lloc' => 'lloc',
        'maintainability' => 'maintainabilityIndex',
        'refactoring_priority' => 'refactoringPriority',
        'coupling' => 'usedFromOutsideCount',
    ];

    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getClassList(string $sort_by = 'refactoring_priority', int $limit = 50, string $filter = ''): string
    {
        try {
            $data = $this->factory->getClassDataProvider()->getTemplateData();
            $classesRaw = $data['classes'] ?? [];
            $classes = is_array($classesRaw) ? $classesRaw : [];

            if (empty($classes)) {
                return 'No class data available.';
            }

            $rows = [];
            foreach ($classes as $collection) {
                if (!$collection instanceof MetricsCollectionInterface) {
                    continue;
                }
                $name = $collection->getString(MetricKey::SINGLE_NAME);
                $fullName = $collection->getString(MetricKey::FULL_NAME) ?: $name;

                if ('' !== $filter && false === stripos($fullName, $filter) && false === stripos($name, $filter)) {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'fullName' => $fullName,
                    'cc' => $collection->getInt(MetricKey::CC),
                    'lloc' => $collection->getInt(MetricKey::LLOC),
                    'maintainabilityIndex' => round($collection->getFloat(MetricKey::MAINTAINABILITY_INDEX), 1),
                    'refactoringPriority' => $collection->getFloat(MetricKey::REFACTORING_PRIORITY),
                    'usedFromOutsideCount' => $collection->getInt(MetricKey::USED_FROM_OUTSIDE_COUNT),
                ];
            }

            $sortKey = self::SORT_KEYS[$sort_by] ?? 'refactoringPriority';

            usort($rows, fn (array $a, array $b): int => $b[$sortKey] <=> $a[$sortKey]);

            $total = count($rows);
            $rows = array_slice($rows, 0, max(1, $limit));

            $lines = [
                "# Class List (sorted by: {$sort_by})",
                "Total: {$total} classes | Showing: ".count($rows),
                '',
                sprintf('%-40s %4s %5s %5s %8s %8s', 'Class', 'CC', 'LLOC', 'MI', 'Priority', 'Coupling'),
                str_repeat('-', 74),
            ];

            foreach ($rows as $row) {
                $rowName = $row['name'];
                $shortName = strlen($rowName) > 38 ? '...'.substr($rowName, -35) : $rowName;
                $lines[] = sprintf('%-40s %4d %5d %5.1f %8.1f %8d',
                    $shortName,
                    $row['cc'],
                    $row['lloc'],
                    $row['maintainabilityIndex'],
                    $row['refactoringPriority'],
                    $row['usedFromOutsideCount']
                );
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving the class list.';
        }
    }
}
