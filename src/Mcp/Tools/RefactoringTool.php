<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class RefactoringTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getRefactoringPriorities(int $limit = 15, float $min_score = 0.0): string
    {
        try {
            $data = $this->factory->getRefactoringPriorityDataProvider()->getTemplateData();
            $rawPriorities = $data['refactoringPriorities'] ?? null;
            $priorities = is_array($rawPriorities) ? $rawPriorities : [];

            $priorities = $this->filterPriorities($priorities, $min_score);
            $total = count($priorities);
            $priorities = array_slice(array_values($priorities), 0, max(1, $limit));

            $lines = $this->buildRefactoringHeader($data, $total, count($priorities));

            foreach ($priorities as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $lines = array_merge($lines, $this->formatPriorityEntry($p));
            }

            if (0 === $total) {
                $lines[] = 'No refactoring candidates found.';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving refactoring priorities.';
        }
    }

    /**
     * @param array<mixed> $priorities
     *
     * @return array<mixed>
     */
    private function filterPriorities(array $priorities, float $min_score): array
    {
        if ($min_score <= 0.0) {
            return $priorities;
        }

        return array_filter($priorities, function (mixed $p) use ($min_score): bool {
            if (!is_array($p)) {
                return false;
            }
            $rawScore = $p['score'] ?? null;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;

            return $score >= $min_score;
        });
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function buildRefactoringHeader(array $data, int $total, int $showing): array
    {
        $rawDistribution = $data['distribution'] ?? null;
        $distribution = is_array($rawDistribution) ? $rawDistribution : [];
        $totalClasses = is_int($data['totalClasses'] ?? null) ? $data['totalClasses'] : 0;
        $avgPriority = is_numeric($data['avgPriority'] ?? null) ? (float) $data['avgPriority'] : 0.0;
        $maxPriority = is_numeric($data['maxPriority'] ?? null) ? (float) $data['maxPriority'] : 0.0;
        $needingRefactoring = is_int($data['classesNeedingRefactoring'] ?? null) ? $data['classesNeedingRefactoring'] : 0;
        $cleanCount = is_int($distribution['clean'] ?? null) ? $distribution['clean'] : 0;
        $lowCount = is_int($distribution['low'] ?? null) ? $distribution['low'] : 0;
        $mediumCount = is_int($distribution['medium'] ?? null) ? $distribution['medium'] : 0;
        $highCount = is_int($distribution['high'] ?? null) ? $distribution['high'] : 0;
        $criticalCount = is_int($distribution['critical'] ?? null) ? $distribution['critical'] : 0;

        return [
            '# Refactoring Priorities',
            '',
            "Classes needing refactoring: {$needingRefactoring} / {$totalClasses}",
            'Avg Priority Score: '.round($avgPriority, 1).' | Max: '.round($maxPriority, 1),
            '',
            'Distribution:',
            "  Clean:    {$cleanCount}",
            "  Low:      {$lowCount}",
            "  Medium:   {$mediumCount}",
            "  High:     {$highCount}",
            "  Critical: {$criticalCount}",
            '',
            "Top {$total} candidates (showing {$showing}):",
            '',
        ];
    }

    /**
     * @param array<mixed> $p
     *
     * @return array{score: float, name: string, cc: int, lloc: int, lcom: float, usedByCount: int, recommendation: string, drivers: array<mixed>}
     */
    private function normalizePriorityEntry(array $p): array
    {
        return [
            'score' => is_numeric($p['score'] ?? null) ? (float) $p['score'] : 0.0,
            'name' => is_string($p['name'] ?? null) ? (string) $p['name'] : '',
            'cc' => is_int($p['cc'] ?? null) ? (int) $p['cc'] : 0,
            'lloc' => is_int($p['lloc'] ?? null) ? (int) $p['lloc'] : 0,
            'lcom' => is_numeric($p['lcom'] ?? null) ? (float) $p['lcom'] : 0.0,
            'usedByCount' => is_int($p['usedFromOutsideCount'] ?? null) ? (int) $p['usedFromOutsideCount'] : 0,
            'recommendation' => is_string($p['recommendation'] ?? null) ? (string) $p['recommendation'] : '',
            'drivers' => is_array($p['drivers'] ?? null) ? (array) $p['drivers'] : [],
        ];
    }

    /**
     * @param array<mixed> $p
     *
     * @return list<string>
     */
    private function formatPriorityEntry(array $p): array
    {
        $entry = $this->normalizePriorityEntry($p);

        $urgency = match (true) {
            $entry['score'] > 75 => 'CRITICAL',
            $entry['score'] > 50 => 'HIGH',
            $entry['score'] > 25 => 'MEDIUM',
            default => 'LOW',
        };

        $lines = [
            "[{$urgency}] {$entry['name']} (score: {$entry['score']})",
            "  CC: {$entry['cc']} | LLOC: {$entry['lloc']} | LCOM: {$entry['lcom']} | Used by: {$entry['usedByCount']}",
        ];

        if ('' !== $entry['recommendation']) {
            $lines[] = "  → {$entry['recommendation']}";
        }

        if (!empty($entry['drivers'])) {
            $driverStrings = array_map(fn ($d): string => is_scalar($d) ? (string) $d : '', $entry['drivers']);
            $lines[] = '  Drivers: '.implode(', ', $driverStrings);
        }

        $lines[] = '';

        return $lines;
    }
}
