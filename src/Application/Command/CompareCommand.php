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
        $argsRaw = $config->get('commandArgs');
        $args = is_array($argsRaw) ? $argsRaw : [];

        if (2 !== count($args)) {
            $output->outNl($formatter->error('Usage: phpcodearcheology compare <report-before.json> <report-after.json>'));

            return 1;
        }

        $fileBefore = is_string($args[0] ?? null) ? $args[0] : '';
        $fileAfter = is_string($args[1] ?? null) ? $args[1] : '';

        foreach ([$fileBefore, $fileAfter] as $file) {
            if (!file_exists($file)) {
                $output->outNl($formatter->error("File not found: $file"));

                return 1;
            }
        }

        $beforeContent = file_get_contents($fileBefore);
        $afterContent = file_get_contents($fileAfter);
        $beforeDecoded = is_string($beforeContent) ? json_decode($beforeContent, true) : null;
        $afterDecoded = is_string($afterContent) ? json_decode($afterContent, true) : null;

        if (!is_array($beforeDecoded) || !is_array($afterDecoded)) {
            $output->outNl($formatter->error('Invalid JSON in one or both files.'));

            return 1;
        }

        $before = $this->toStringKeyedArray($beforeDecoded);
        $after = $this->toStringKeyedArray($afterDecoded);

        $output->outNl($formatter->bold('Report Comparison'));
        $output->outNl($formatter->dim("Before: $fileBefore"));
        $output->outNl($formatter->dim("After:  $fileAfter"));

        $comparator = new ReportComparator();

        $this->renderMetrics($comparator->compareMetrics($before, $after), $output, $formatter);
        $this->renderProblemCounts($comparator->compareProblemCounts($before, $after), $output, $formatter);
        $this->renderProblems($comparator->compareProblems($before, $after), $output, $formatter);

        return 0;
    }

    /**
     * Convert a mixed-key array to a string-keyed array, dropping non-string keys.
     *
     * @param array<mixed, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function toStringKeyedArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param list<array{name: string, before: string, after: string, delta: string, adjustedDelta: float|int}> $rows */
    private function renderMetrics(array $rows, CliOutput $output, CliFormatter $formatter): void
    {
        $table = new TerminalTable($output, $formatter);
        $table->setHeaders(['Metric', 'Before', 'After', 'Delta']);

        foreach ($rows as $row) {
            $adjustedDelta = $row['adjustedDelta'];
            $table->setColumnFormatter(3, function (mixed $val, string $padded) use ($formatter, $adjustedDelta): string {
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

    /** @param list<array{level: string, before: int, after: int, delta: string}> $rows */
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

    /**
     * @param array{new: list<array<mixed>>, resolved: list<array<mixed>>} $result
     */
    private function renderProblems(array $result, CliOutput $output, CliFormatter $formatter): void
    {
        $newProblems = $result['new'];
        $resolvedProblems = $result['resolved'];

        if (!empty($newProblems)) {
            $output->outNl();
            $output->outNl($formatter->bold('  New Problems').$formatter->error(' (+'.count($newProblems).')'));
            foreach (array_slice($newProblems, 0, 10) as $p) {
                $level = is_string($p['level'] ?? null) ? $p['level'] : 'unknown';
                $entityId = is_string($p['entityId'] ?? null) ? $p['entityId'] : '';
                $message = is_string($p['message'] ?? null) ? $p['message'] : '';
                $levelStr = strtoupper($level);
                $output->outNl('  '.$formatter->error("[$levelStr]").' '.$entityId.': '.$message);
            }
            if (count($newProblems) > 10) {
                $output->outNl($formatter->dim('  ... and '.(count($newProblems) - 10).' more.'));
            }
        }

        if (!empty($resolvedProblems)) {
            $output->outNl();
            $output->outNl($formatter->bold('  Resolved Problems').$formatter->success(' (-'.count($resolvedProblems).')'));
            foreach (array_slice($resolvedProblems, 0, 10) as $p) {
                $level = is_string($p['level'] ?? null) ? $p['level'] : 'unknown';
                $entityId = is_string($p['entityId'] ?? null) ? $p['entityId'] : '';
                $message = is_string($p['message'] ?? null) ? $p['message'] : '';
                $levelStr = strtoupper($level);
                $output->outNl('  '.$formatter->success("[$levelStr]").' '.$entityId.': '.$message);
            }
            if (count($resolvedProblems) > 10) {
                $output->outNl($formatter->dim('  ... and '.(count($resolvedProblems) - 10).' more.'));
            }
        }

        if (empty($newProblems) && empty($resolvedProblems)) {
            $output->outNl();
            $output->outNl($formatter->dim('  No problem changes detected.'));
        }

        $output->outNl();
    }
}
