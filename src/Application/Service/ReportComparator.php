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
     * @param array<mixed> $before
     * @param array<mixed> $after
     *
     * @return list<array{name: string, before: string, after: string, delta: string, adjustedDelta: float|int}>
     */
    public function compareMetrics(array $before, array $after): array
    {
        $projectBefore = $before['project'] ?? null;
        $projectAfter = $after['project'] ?? null;
        $metricsBefore = is_array($projectBefore) && is_array($projectBefore['metrics'] ?? null) ? $projectBefore['metrics'] : [];
        $metricsAfter = is_array($projectAfter) && is_array($projectAfter['metrics'] ?? null) ? $projectAfter['metrics'] : [];

        $rows = [];

        foreach (self::INTERESTING_KEYS as $key) {
            $mBefore = is_array($metricsBefore[$key] ?? null) ? $metricsBefore[$key] : null;
            $mAfter = is_array($metricsAfter[$key] ?? null) ? $metricsAfter[$key] : null;

            if (null === $mBefore && null === $mAfter) {
                continue;
            }

            $nameBefore = is_string($mBefore['name'] ?? null) ? $mBefore['name'] : null;
            $nameAfter = is_string($mAfter['name'] ?? null) ? $mAfter['name'] : null;
            $name = $nameAfter ?? $nameBefore ?? $key;
            $valBefore = $mBefore['value'] ?? 0;
            $valAfter = $mAfter['value'] ?? 0;

            if (!is_numeric($valBefore) || !is_numeric($valAfter)) {
                $rows[] = [
                    'name' => $name,
                    'before' => is_scalar($valBefore) ? (string) $valBefore : '?',
                    'after' => is_scalar($valAfter) ? (string) $valAfter : '?',
                    'delta' => '-',
                    'adjustedDelta' => 0,
                ];
                continue;
            }

            $delta = round((float) $valAfter - (float) $valBefore, 2);
            $deltaStr = $delta > 0 ? '+'.$delta : (string) $delta;

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
     * @param array<mixed> $before
     * @param array<mixed> $after
     *
     * @return list<array{level: string, before: int, after: int, delta: string}>
     */
    public function compareProblemCounts(array $before, array $after): array
    {
        $problemsBefore = is_array($before['problems'] ?? null) ? $before['problems'] : [];
        $problemsAfter = is_array($after['problems'] ?? null) ? $after['problems'] : [];
        $countBefore = $this->countByLevel($problemsBefore);
        $countAfter = $this->countByLevel($problemsAfter);

        $rows = [];
        foreach (['error', 'warning', 'info'] as $level) {
            $b = $countBefore[$level] ?? 0;
            $a = $countAfter[$level] ?? 0;
            $delta = $a - $b;
            $rows[] = [
                'level' => ucfirst($level),
                'before' => $b,
                'after' => $a,
                'delta' => $delta > 0 ? '+'.$delta : (string) $delta,
            ];
        }

        $totalBefore = array_sum($countBefore);
        $totalAfter = array_sum($countAfter);
        $totalDelta = $totalAfter - $totalBefore;
        $rows[] = [
            'level' => 'Total',
            'before' => $totalBefore,
            'after' => $totalAfter,
            'delta' => $totalDelta > 0 ? '+'.$totalDelta : (string) $totalDelta,
        ];

        return $rows;
    }

    /**
     * Find new and resolved problems between two reports.
     *
     * @param array<mixed> $before
     * @param array<mixed> $after
     *
     * @return array{new: list<array<mixed>>, resolved: list<array<mixed>>}
     */
    public function compareProblems(array $before, array $after): array
    {
        $problemsBefore = is_array($before['problems'] ?? null) ? $before['problems'] : [];
        $problemsAfter = is_array($after['problems'] ?? null) ? $after['problems'] : [];

        $signaturesBefore = $this->problemSignatures($problemsBefore);
        $signaturesAfter = $this->problemSignatures($problemsAfter);

        $newProblems = [];
        foreach ($problemsAfter as $problem) {
            if (!is_array($problem)) {
                continue;
            }
            $sig = $this->signature($problem);
            if (!isset($signaturesBefore[$sig])) {
                $newProblems[] = $problem;
            }
        }

        $resolvedProblems = [];
        foreach ($problemsBefore as $problem) {
            if (!is_array($problem)) {
                continue;
            }
            $sig = $this->signature($problem);
            if (!isset($signaturesAfter[$sig])) {
                $resolvedProblems[] = $problem;
            }
        }

        return ['new' => $newProblems, 'resolved' => $resolvedProblems];
    }

    /**
     * @param array<mixed> $problems
     *
     * @return array<string, int>
     */
    private function countByLevel(array $problems): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($problems as $p) {
            if (!is_array($p)) {
                continue;
            }
            $level = is_string($p['level'] ?? null) ? $p['level'] : 'info';
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<mixed> $problems
     *
     * @return array<string, bool>
     */
    private function problemSignatures(array $problems): array
    {
        $sigs = [];
        foreach ($problems as $p) {
            if (!is_array($p)) {
                continue;
            }
            $sigs[$this->signature($p)] = true;
        }

        return $sigs;
    }

    /** @param array<mixed> $problem */
    private function signature(array $problem): string
    {
        $entityId = is_string($problem['entityId'] ?? null) ? $problem['entityId'] : '';
        $message = is_string($problem['message'] ?? null) ? $problem['message'] : '';
        $level = is_string($problem['level'] ?? null) ? $problem['level'] : '';

        return $entityId.'|'.$message.'|'.$level;
    }
}
