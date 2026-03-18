<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class QuickOutput
{
    public function __construct(
        private readonly MetricsController $metricsController,
        private readonly CliOutput         $output,
        private readonly CliFormatter      $formatter,
    )
    {
    }

    public function render(): void
    {
        $this->renderProjectOverview();
        $this->renderTopFilesByComplexity();
        $this->renderTopClassesByComplexity();
        $this->renderSummary();
    }

    private function renderProjectOverview(): void
    {
        $get = fn(string $key) => $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->getValue() ?? 0;

        $files = number_format((int) $get('overallFiles'));
        $classes = number_format((int) $get('overallClasses'));
        $methods = number_format((int) $get('overallMethodsCount'));
        $lloc = number_format((int) $get('overallLloc'));

        $this->output->outNl();
        $this->output->outNl($this->formatter->bold('  Quick Analysis'));
        $this->output->outNl(sprintf(
            '  %s files | %s classes | %s methods | %s LLOC',
            $this->formatter->info($files),
            $this->formatter->info($classes),
            $this->formatter->info($methods),
            $this->formatter->info($lloc),
        ));
    }

    private function renderTopFilesByComplexity(): void
    {
        $files = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof FileMetricsCollection) {
                continue;
            }

            $cc = $collection->get('cc')?->getValue() ?? 0;
            $loc = $collection->get('loc')?->getValue() ?? 0;
            $mi = $collection->get('mi')?->getValue() ?? 0;
            $fileName = $collection->get('fileName')?->getValue() ?? $collection->getName();

            $files[] = [
                'name' => $fileName,
                'loc' => $loc,
                'cc' => $cc,
                'mi' => round((float) $mi, 1),
            ];
        }

        usort($files, fn($a, $b) => $b['cc'] <=> $a['cc']);
        $top = array_slice($files, 0, 10);

        if (empty($top)) {
            return;
        }

        $this->output->outNl();
        $this->output->outNl($this->formatter->bold('  Top 10 Files by Complexity'));

        $table = new TerminalTable($this->output, $this->formatter);
        $table->setHeaders(['File', 'LOC', 'CC', 'MI']);

        $table->setColumnFormatter(2, fn($val, $padded) => match (true) {
            $val > 20 => $this->formatter->error($padded),
            $val > 10 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        $table->setColumnFormatter(3, fn($val, $padded) => match (true) {
            $val < 50 => $this->formatter->error($padded),
            $val < 70 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        foreach ($top as $file) {
            $name = $file['name'];
            if (mb_strlen($name) > 50) {
                $name = '...' . mb_substr($name, -47);
            }
            $table->addRow([$name, $file['loc'], $file['cc'], $file['mi']]);
        }

        $table->render();
    }

    private function renderTopClassesByComplexity(): void
    {
        $classes = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $cc = $collection->get('cc')?->getValue() ?? 0;
            $methods = $collection->get('numberOfMethods')?->getValue() ?? 0;
            $mi = $collection->get('mi')?->getValue() ?? 0;

            $classes[] = [
                'name' => $collection->getName(),
                'methods' => $methods,
                'cc' => $cc,
                'mi' => round((float) $mi, 1),
            ];
        }

        usort($classes, fn($a, $b) => $b['cc'] <=> $a['cc']);
        $top = array_slice($classes, 0, 10);

        if (empty($top)) {
            return;
        }

        $this->output->outNl();
        $this->output->outNl($this->formatter->bold('  Top 10 Classes by Complexity'));

        $table = new TerminalTable($this->output, $this->formatter);
        $table->setHeaders(['Class', 'Methods', 'CC', 'MI']);

        $table->setColumnFormatter(2, fn($val, $padded) => match (true) {
            $val > 20 => $this->formatter->error($padded),
            $val > 10 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        $table->setColumnFormatter(3, fn($val, $padded) => match (true) {
            $val < 50 => $this->formatter->error($padded),
            $val < 70 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        foreach ($top as $class) {
            $name = $class['name'];
            if (mb_strlen($name) > 50) {
                $name = '...' . mb_substr($name, -47);
            }
            $table->addRow([$name, $class['methods'], $class['cc'], $class['mi']]);
        }

        $table->render();
    }

    private function renderSummary(): void
    {
        $get = fn(string $key) => $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->getValue() ?? 0;

        $avgCC = round((float) $get('overallAvgCC'), 2);
        $avgMI = round((float) $get('overallAvgMI'), 1);

        $ccStr = match (true) {
            $avgCC > 15 => $this->formatter->error((string) $avgCC),
            $avgCC > 8 => $this->formatter->warning((string) $avgCC),
            default => $this->formatter->success((string) $avgCC),
        };

        $miStr = match (true) {
            $avgMI < 50 => $this->formatter->error((string) $avgMI),
            $avgMI < 70 => $this->formatter->warning((string) $avgMI),
            default => $this->formatter->success((string) $avgMI),
        };

        $this->output->outNl();
        $this->output->outNl(sprintf('  Avg CC: %s  |  Avg MI: %s', $ccStr, $miStr));
        $this->output->outNl();
    }
}
