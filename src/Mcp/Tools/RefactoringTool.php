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
            $rawDistribution = $data['distribution'] ?? null;
            $distribution = is_array($rawDistribution) ? $rawDistribution : [];
            $totalClasses = is_int($data['totalClasses'] ?? null) ? $data['totalClasses'] : 0;
            $rawAvgPriority = $data['avgPriority'] ?? null;
            $avgPriority = is_numeric($rawAvgPriority) ? (float) $rawAvgPriority : 0.0;
            $rawMaxPriority = $data['maxPriority'] ?? null;
            $maxPriority = is_numeric($rawMaxPriority) ? (float) $rawMaxPriority : 0.0;
            $needingRefactoring = is_int($data['classesNeedingRefactoring'] ?? null) ? $data['classesNeedingRefactoring'] : 0;

            if ($min_score > 0.0) {
                $priorities = array_filter($priorities, function (mixed $p) use ($min_score): bool {
                    if (!is_array($p)) {
                        return false;
                    }
                    $rawScore = $p['score'] ?? null;
                    $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;

                    return $score >= $min_score;
                });
            }

            $total = count($priorities);
            $priorities = array_slice(array_values($priorities), 0, max(1, $limit));

            $cleanCount = is_int($distribution['clean'] ?? null) ? $distribution['clean'] : 0;
            $lowCount = is_int($distribution['low'] ?? null) ? $distribution['low'] : 0;
            $mediumCount = is_int($distribution['medium'] ?? null) ? $distribution['medium'] : 0;
            $highCount = is_int($distribution['high'] ?? null) ? $distribution['high'] : 0;
            $criticalCount = is_int($distribution['critical'] ?? null) ? $distribution['critical'] : 0;

            $lines = [
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
                "Top {$total} candidates (showing ".count($priorities).'):',
                '',
            ];

            foreach ($priorities as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $rawScore = $p['score'] ?? null;
                $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;
                $name = is_string($p['name'] ?? null) ? $p['name'] : '';
                $cc = is_int($p['cc'] ?? null) ? $p['cc'] : 0;
                $lloc = is_int($p['lloc'] ?? null) ? $p['lloc'] : 0;
                $rawLcom = $p['lcom'] ?? null;
                $lcom = is_numeric($rawLcom) ? (float) $rawLcom : 0.0;
                $usedByCount = is_int($p['usedFromOutsideCount'] ?? null) ? $p['usedFromOutsideCount'] : 0;
                $recommendation = is_string($p['recommendation'] ?? null) ? $p['recommendation'] : '';
                $rawDrivers = $p['drivers'] ?? null;
                $drivers = is_array($rawDrivers) ? $rawDrivers : [];

                $urgency = match (true) {
                    $score > 75 => 'CRITICAL',
                    $score > 50 => 'HIGH',
                    $score > 25 => 'MEDIUM',
                    default => 'LOW',
                };

                $lines[] = "[{$urgency}] {$name} (score: {$score})";
                $lines[] = "  CC: {$cc} | LLOC: {$lloc} | LCOM: {$lcom} | Used by: {$usedByCount}";

                if ('' !== $recommendation) {
                    $lines[] = "  → {$recommendation}";
                }

                if (!empty($drivers)) {
                    $driverStrings = array_map(fn ($d): string => is_scalar($d) ? (string) $d : '', $drivers);
                    $lines[] = '  Drivers: '.implode(', ', $driverStrings);
                }

                $lines[] = '';
            }

            if (0 === $total) {
                $lines[] = 'No refactoring candidates found.';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving refactoring priorities.';
        }
    }
}
