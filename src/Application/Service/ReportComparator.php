<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class ReportComparator
{
    private const LOWER_IS_BETTER = [
        'overallErrorCount', 'overallWarningCount', 'overallInformationCount',
        'overallAvgCC', 'overallMaxCC', 'overallTechnicalDebtScore',
        'overallDuplicationRate', 'overallHtmlLoc',
    ];

    private const HIGHER_IS_BETTER = [
        'overallAvgMI', 'healthScore', 'overallCommentWeight',
    ];

    private const INTERESTING_KEYS = [
        'healthScore', 'overallFiles', 'overallClasses', 'overallLoc', 'overallLloc',
        'overallAvgCC', 'overallAvgMI', 'overallMaxCC',
        'overallErrorCount', 'overallWarningCount', 'overallInformationCount',
        'overallTechnicalDebtScore', 'overallDuplicationRate',
    ];

    /**
     * Compare project metrics between two reports.
     *
     * @return array{rows: array[], keys: string[]} Each row: [name, before, after, delta, adjustedDelta]
     */
    public function compareMetrics(array $before, array $after): array
    {
        $metricsBefore = $before['project']['metrics'] ?? [];
        $metricsAfter = $after['project']['metrics'] ?? [];

        $rows = [];

        foreach (self::INTERESTING_KEYS as $key) {
            if (!isset($metricsBefore[$key]) && !isset($metricsAfter[$key])) {
                continue;
            }

            $name = $metricsAfter[$key]['name'] ?? $metricsBefore[$key]['name'] ?? $key;
            $valBefore = $metricsBefore[$key]['value'] ?? 0;
            $valAfter = $metricsAfter[$key]['value'] ?? 0;

            if (!is_numeric($valBefore) || !is_numeric($valAfter)) {
                $rows[] = [
                    'name' => $name,
                    'before' => (string) $valBefore,
                    'after' => (string) $valAfter,
                    'delta' => '-',
                    'adjustedDelta' => 0,
                ];
                continue;
            }

            $delta = round($valAfter - $valBefore, 2);
            $deltaStr = $delta > 0 ? '+' . $delta : (string) $delta;

            if (in_array($key, self::LOWER_IS_BETTER, true)) {
                $adjustedDelta = -$delta;
            } elseif (in_array($key, self::HIGHER_IS_BETTER, true)) {
                $adjustedDelta = $delta;
            } else {
                $adjustedDelta = 0;
            }

            $rows[] = [
                'name' => $name,
                'before' => (string) $valBefore,
                'after' => (string) $valAfter,
                'delta' => $deltaStr,
                'adjustedDelta' => $adjustedDelta,
            ];
        }

        return $rows;
    }

    /**
     * Count problems by level for both reports.
     *
     * @return array{rows: array[], totalBefore: int, totalAfter: int}
     */
    public function compareProblemCounts(array $before, array $after): array
    {
        $countBefore = $this->countByLevel($before['problems'] ?? []);
        $countAfter = $this->countByLevel($after['problems'] ?? []);

        $rows = [];
        foreach (['error', 'warning', 'info'] as $level) {
            $b = $countBefore[$level] ?? 0;
            $a = $countAfter[$level] ?? 0;
            $delta = $a - $b;
            $rows[] = [
                'level' => ucfirst($level),
                'before' => $b,
                'after' => $a,
                'delta' => $delta > 0 ? '+' . $delta : (string) $delta,
            ];
        }

        $totalBefore = array_sum($countBefore);
        $totalAfter = array_sum($countAfter);
        $totalDelta = $totalAfter - $totalBefore;
        $rows[] = [
            'level' => 'Total',
            'before' => $totalBefore,
            'after' => $totalAfter,
            'delta' => $totalDelta > 0 ? '+' . $totalDelta : (string) $totalDelta,
        ];

        return $rows;
    }

    /**
     * Find new and resolved problems between two reports.
     *
     * @return array{new: array, resolved: array}
     */
    public function compareProblems(array $before, array $after): array
    {
        $signaturesBefore = $this->problemSignatures($before['problems'] ?? []);
        $signaturesAfter = $this->problemSignatures($after['problems'] ?? []);

        $newProblems = [];
        foreach ($after['problems'] ?? [] as $problem) {
            $sig = $this->signature($problem);
            if (!isset($signaturesBefore[$sig])) {
                $newProblems[] = $problem;
            }
        }

        $resolvedProblems = [];
        foreach ($before['problems'] ?? [] as $problem) {
            $sig = $this->signature($problem);
            if (!isset($signaturesAfter[$sig])) {
                $resolvedProblems[] = $problem;
            }
        }

        return ['new' => $newProblems, 'resolved' => $resolvedProblems];
    }

    private function countByLevel(array $problems): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($problems as $p) {
            $level = $p['level'] ?? 'info';
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }
        return $counts;
    }

    private function problemSignatures(array $problems): array
    {
        $sigs = [];
        foreach ($problems as $p) {
            $sigs[$this->signature($p)] = true;
        }
        return $sigs;
    }

    private function signature(array $problem): string
    {
        return ($problem['entityId'] ?? '') . '|' . ($problem['message'] ?? '') . '|' . ($problem['level'] ?? '');
    }
}
