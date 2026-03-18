<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Command;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\ReportComparator;
use PhpCodeArch\Application\TerminalTable;

class CompareCommand
{
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

        $comparator = new ReportComparator();

        $this->renderMetrics($comparator->compareMetrics($before, $after), $output, $formatter);
        $this->renderProblemCounts($comparator->compareProblemCounts($before, $after), $output, $formatter);
        $this->renderProblems($comparator->compareProblems($before, $after), $output, $formatter);

        return 0;
    }

    private function renderMetrics(array $rows, CliOutput $output, CliFormatter $formatter): void
    {
        $table = new TerminalTable($output, $formatter);
        $table->setHeaders(['Metric', 'Before', 'After', 'Delta']);

        foreach ($rows as $row) {
            $adjustedDelta = $row['adjustedDelta'];
            $table->setColumnFormatter(3, function ($val, $padded) use ($formatter, $adjustedDelta) {
                if ($adjustedDelta > 0) {
                    return $formatter->success($padded);
                }
                if ($adjustedDelta < 0) {
                    return $formatter->error($padded);
                }
                return $formatter->dim($padded);
            });

            $table->addRow([$row['name'], $row['before'], $row['after'], $row['delta']]);
        }

        $output->outNl();
        $output->outNl($formatter->bold('  Metrics'));
        $table->render();
    }

    private function renderProblemCounts(array $rows, CliOutput $output, CliFormatter $formatter): void
    {
        $table = new TerminalTable($output, $formatter);
        $table->setHeaders(['Level', 'Before', 'After', 'Delta']);

        foreach ($rows as $row) {
            $table->addRow([$row['level'], $row['before'], $row['after'], $row['delta']]);
        }

        $output->outNl();
        $output->outNl($formatter->bold('  Problems'));
        $table->render();
    }

    private function renderProblems(array $result, CliOutput $output, CliFormatter $formatter): void
    {
        $newProblems = $result['new'];
        $resolvedProblems = $result['resolved'];

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
}
