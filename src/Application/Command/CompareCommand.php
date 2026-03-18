<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Command;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\TerminalTable;

class CompareCommand
{
    private const LOWER_IS_BETTER = [
        'overallErrorCount', 'overallWarningCount', 'overallInformationCount',
        'overallAvgCC', 'overallMaxCC', 'overallTechnicalDebtScore',
        'overallDuplicationRate', 'overallHtmlLoc',
    ];

    private const HIGHER_IS_BETTER = [
        'overallAvgMI', 'healthScore', 'overallCommentWeight',
    ];

    public function execute(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        $args = $config->get('commandArgs') ?? [];

        if (count($args) !== 2) {
            $output->outNl($formatter->error('Usage: phpcodearcheology compare <report-before.json> <report-after.json>'));
            return 1;
        }

        [$fileBefore, $fileAfter] = $args;

        foreach ([$fileBefore, $fileAfter] as $file) {
            if (!file_exists($file)) {
                $output->outNl($formatter->error("File not found: $file"));
                return 1;
            }
        }

        $before = json_decode(file_get_contents($fileBefore), true);
        $after = json_decode(file_get_contents($fileAfter), true);

        if ($before === null || $after === null) {
            $output->outNl($formatter->error('Invalid JSON in one or both files.'));
            return 1;
        }

        $output->outNl($formatter->bold('Report Comparison'));
        $output->outNl($formatter->dim("Before: $fileBefore"));
        $output->outNl($formatter->dim("After:  $fileAfter"));

        $this->compareMetrics($before, $after, $output, $formatter);
        $this->compareProblemCounts($before, $after, $output, $formatter);
        $this->compareProblems($before, $after, $output, $formatter);

        return 0;
    }

    private function compareMetrics(array $before, array $after, CliOutput $output, CliFormatter $formatter): void
    {
        $metricsBefore = $before['project']['metrics'] ?? [];
        $metricsAfter = $after['project']['metrics'] ?? [];

        $allKeys = array_unique(array_merge(array_keys($metricsBefore), array_keys($metricsAfter)));

        $table = new TerminalTable($output, $formatter);
        $table->setHeaders(['Metric', 'Before', 'After', 'Delta']);

        $table->setColumnFormatter(3, function ($val, $padded) use ($formatter) {
            if (!is_numeric($val)) {
                return $formatter->dim($padded);
            }
            if ($val > 0) {
                return $formatter->error('+' . $padded);
            }
            if ($val < 0) {
                return $formatter->success($padded);
            }
            return $formatter->dim($padded);
        });

        $interestingKeys = [
            'healthScore', 'overallFiles', 'overallClasses', 'overallLoc', 'overallLloc',
            'overallAvgCC', 'overallAvgMI', 'overallMaxCC',
            'overallErrorCount', 'overallWarningCount', 'overallInformationCount',
            'overallTechnicalDebtScore', 'overallDuplicationRate',
        ];

        foreach ($interestingKeys as $key) {
            if (!isset($metricsBefore[$key]) && !isset($metricsAfter[$key])) {
                continue;
            }

            $name = $metricsAfter[$key]['name'] ?? $metricsBefore[$key]['name'] ?? $key;
            $valBefore = $metricsBefore[$key]['value'] ?? 0;
            $valAfter = $metricsAfter[$key]['value'] ?? 0;

            if (!is_numeric($valBefore) || !is_numeric($valAfter)) {
                $table->addRow([$name, (string) $valBefore, (string) $valAfter, '-']);
                continue;
            }

            $delta = round($valAfter - $valBefore, 2);
            $deltaStr = $delta > 0 ? '+' . $delta : (string) $delta;

            // Adjust sign for "lower is better" metrics
            if (in_array($key, self::LOWER_IS_BETTER, true)) {
                $adjustedDelta = -$delta; // Negative delta is good
            } elseif (in_array($key, self::HIGHER_IS_BETTER, true)) {
                $adjustedDelta = $delta; // Positive delta is good
            } else {
                $adjustedDelta = 0; // Neutral
            }

            $table->setColumnFormatter(3, function ($val, $padded) use ($formatter, $adjustedDelta) {
                if ($adjustedDelta > 0) {
                    return $formatter->success($padded);
                }
                if ($adjustedDelta < 0) {
                    return $formatter->error($padded);
                }
                return $formatter->dim($padded);
            });

            $table->addRow([$name, (string) $valBefore, (string) $valAfter, $deltaStr]);
        }

        $output->outNl();
        $output->outNl($formatter->bold('  Metrics'));
        $table->render();
    }

    private function compareProblemCounts(array $before, array $after, CliOutput $output, CliFormatter $formatter): void
    {
        $countBefore = $this->countByLevel($before['problems'] ?? []);
        $countAfter = $this->countByLevel($after['problems'] ?? []);

        $table = new TerminalTable($output, $formatter);
        $table->setHeaders(['Level', 'Before', 'After', 'Delta']);

        foreach (['error', 'warning', 'info'] as $level) {
            $b = $countBefore[$level] ?? 0;
            $a = $countAfter[$level] ?? 0;
            $delta = $a - $b;
            $deltaStr = $delta > 0 ? '+' . $delta : (string) $delta;
            $table->addRow([ucfirst($level), $b, $a, $deltaStr]);
        }

        $totalBefore = array_sum($countBefore);
        $totalAfter = array_sum($countAfter);
        $totalDelta = $totalAfter - $totalBefore;
        $table->addRow(['Total', $totalBefore, $totalAfter, $totalDelta > 0 ? '+' . $totalDelta : (string) $totalDelta]);

        $output->outNl();
        $output->outNl($formatter->bold('  Problems'));
        $table->render();
    }

    private function compareProblems(array $before, array $after, CliOutput $output, CliFormatter $formatter): void
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

        if (!empty($newProblems)) {
            $output->outNl();
            $output->outNl($formatter->bold('  New Problems') . $formatter->error(' (+' . count($newProblems) . ')'));
            foreach (array_slice($newProblems, 0, 10) as $p) {
                $levelStr = strtoupper($p['level'] ?? 'unknown');
                $output->outNl('  ' . $formatter->error("[$levelStr]") . ' ' . ($p['entityId'] ?? '') . ': ' . ($p['message'] ?? ''));
            }
            if (count($newProblems) > 10) {
                $output->outNl($formatter->dim('  ... and ' . (count($newProblems) - 10) . ' more.'));
            }
        }

        if (!empty($resolvedProblems)) {
            $output->outNl();
            $output->outNl($formatter->bold('  Resolved Problems') . $formatter->success(' (-' . count($resolvedProblems) . ')'));
            foreach (array_slice($resolvedProblems, 0, 10) as $p) {
                $levelStr = strtoupper($p['level'] ?? 'unknown');
                $output->outNl('  ' . $formatter->success("[$levelStr]") . ' ' . ($p['entityId'] ?? '') . ': ' . ($p['message'] ?? ''));
            }
            if (count($resolvedProblems) > 10) {
                $output->outNl($formatter->dim('  ... and ' . (count($resolvedProblems) - 10) . ' more.'));
            }
        }

        if (empty($newProblems) && empty($resolvedProblems)) {
            $output->outNl();
            $output->outNl($formatter->dim('  No problem changes detected.'));
        }

        $output->outNl();
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
