<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class RefactoringTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getRefactoringPriorities(int $limit = 15, float $min_score = 0.0): string
    {
        try {
            $data = $this->factory->getRefactoringPriorityDataProvider()->getTemplateData();
            $priorities = $data['refactoringPriorities'] ?? [];
            $distribution = $data['distribution'] ?? [];
            $totalClasses = $data['totalClasses'] ?? 0;
            $avgPriority = $data['avgPriority'] ?? 0;
            $maxPriority = $data['maxPriority'] ?? 0;
            $needingRefactoring = $data['classesNeedingRefactoring'] ?? 0;

            if ($min_score > 0.0) {
                $priorities = array_filter($priorities, fn($p) => $p['score'] >= $min_score);
            }

            $total = count($priorities);
            $priorities = array_slice(array_values($priorities), 0, max(1, $limit));

            $lines = [
                "# Refactoring Priorities",
                "",
                "Classes needing refactoring: {$needingRefactoring} / {$totalClasses}",
                "Avg Priority Score: " . round((float) $avgPriority, 1) . " | Max: " . round((float) $maxPriority, 1),
                "",
                "Distribution:",
                "  Clean:    " . ($distribution['clean'] ?? 0),
                "  Low:      " . ($distribution['low'] ?? 0),
                "  Medium:   " . ($distribution['medium'] ?? 0),
                "  High:     " . ($distribution['high'] ?? 0),
                "  Critical: " . ($distribution['critical'] ?? 0),
                "",
                "Top {$total} candidates (showing " . count($priorities) . "):",
                "",
            ];

            foreach ($priorities as $p) {
                $urgency = match (true) {
                    $p['score'] > 75 => 'CRITICAL',
                    $p['score'] > 50 => 'HIGH',
                    $p['score'] > 25 => 'MEDIUM',
                    default => 'LOW',
                };

                $lines[] = "[{$urgency}] {$p['name']} (score: {$p['score']})";
                $lines[] = "  CC: {$p['cc']} | LLOC: {$p['lloc']} | LCOM: {$p['lcom']} | Used by: {$p['usedFromOutsideCount']}";

                if ($p['recommendation'] !== '') {
                    $lines[] = "  → {$p['recommendation']}";
                }

                if (!empty($p['drivers'])) {
                    $lines[] = "  Drivers: " . implode(', ', (array) $p['drivers']);
                }

                $lines[] = "";
            }

            if ($total === 0) {
                $lines[] = "No refactoring candidates found.";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error retrieving refactoring priorities: " . $e->getMessage();
        }
    }
}
