<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

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
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getClassList(string $sort_by = 'refactoring_priority', int $limit = 50, string $filter = ''): string
    {
        try {
            $data = $this->factory->getClassDataProvider()->getTemplateData();
            $classes = $data['classes'] ?? [];

            if (empty($classes)) {
                return "No class data available.";
            }

            $rows = [];
            foreach ($classes as $collection) {
                $name = $collection->get('singleName')?->getValue() ?? '';
                $fullName = $collection->get('fullName')?->getValue() ?? $name;

                if ($filter !== '' && stripos($fullName, $filter) === false && stripos($name, $filter) === false) {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'fullName' => $fullName,
                    'cc' => $collection->get('cc')?->getValue() ?? 0,
                    'lloc' => $collection->get('lloc')?->getValue() ?? 0,
                    'maintainabilityIndex' => round((float) ($collection->get('maintainabilityIndex')?->getValue() ?? 0), 1),
                    'refactoringPriority' => $collection->get('refactoringPriority')?->getValue() ?? 0,
                    'usedFromOutsideCount' => $collection->get('usedFromOutsideCount')?->getValue() ?? 0,
                ];
            }

            $sortKey = self::SORT_KEYS[$sort_by] ?? 'refactoringPriority';
            $isNumeric = $sortKey !== 'singleName';

            usort($rows, function ($a, $b) use ($sortKey, $isNumeric) {
                if ($isNumeric) {
                    return $b[$sortKey] <=> $a[$sortKey];
                }
                return strcmp((string) $a[$sortKey], (string) $b[$sortKey]);
            });

            $total = count($rows);
            $rows = array_slice($rows, 0, max(1, $limit));

            $lines = [
                "# Class List (sorted by: {$sort_by})",
                "Total: {$total} classes | Showing: " . count($rows),
                "",
                sprintf("%-40s %4s %5s %5s %8s %8s", "Class", "CC", "LLOC", "MI", "Priority", "Coupling"),
                str_repeat("-", 74),
            ];

            foreach ($rows as $row) {
                $shortName = strlen($row['name']) > 38 ? '...' . substr($row['name'], -35) : $row['name'];
                $lines[] = sprintf("%-40s %4d %5d %5.1f %8d %8d",
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
            return "Error retrieving class list: " . $e->getMessage();
        }
    }
}
