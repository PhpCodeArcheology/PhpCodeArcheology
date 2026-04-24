<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;

class QuickOutput
{
    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsRegistryInterface $registry,
        private readonly CliOutput $output,
        private readonly CliFormatter $formatter,
    ) {
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
        $getInt = fn (string $key): int => $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->asInt() ?? 0;

        $files = number_format($getInt(MetricKey::OVERALL_FILES));
        $classes = number_format($getInt(MetricKey::OVERALL_CLASSES));
        $methods = number_format($getInt(MetricKey::OVERALL_METHODS_COUNT));
        $lloc = number_format($getInt(MetricKey::OVERALL_LLOC));

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

        foreach ($this->registry->getAllCollections() as $collection) {
            if (!$collection instanceof FileMetricsCollection) {
                continue;
            }

            $cc = $collection->getInt(MetricKey::CC);
            $loc = $collection->getInt(MetricKey::LOC);
            $mi = $collection->get('mi')?->asFloat() ?? 0.0;
            $fileName = $collection->getString(MetricKey::FILE_NAME) ?: $collection->getName();

            $files[] = [
                'name' => $fileName,
                'loc' => $loc,
                'cc' => $cc,
                'mi' => round($mi, 1),
            ];
        }

        usort($files, fn (array $a, array $b): int => $b['cc'] <=> $a['cc']);
        $top = array_slice($files, 0, 10);

        if ([] === $top) {
            return;
        }

        $this->output->outNl();
        $this->output->outNl($this->formatter->bold('  Top 10 Files by Complexity'));

        $table = new TerminalTable($this->output, $this->formatter);
        $table->setHeaders(['File', 'LOC', 'CC', 'MI']);

        $table->setColumnFormatter(2, fn ($val, string $padded): string => match (true) {
            $val > 20 => $this->formatter->error($padded),
            $val > 10 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        $table->setColumnFormatter(3, fn ($val, string $padded): string => match (true) {
            $val < 50 => $this->formatter->error($padded),
            $val < 70 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        foreach ($top as $file) {
            $name = $file['name'];
            if (mb_strlen((string) $name) > 50) {
                $name = '...'.mb_substr((string) $name, -47);
            }
            $table->addRow([$name, $file['loc'], $file['cc'], $file['mi']]);
        }

        $table->render();
    }

    private function renderTopClassesByComplexity(): void
    {
        $classes = [];

        foreach ($this->registry->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $cc = $collection->getInt(MetricKey::CC);
            $methods = $collection->get('numberOfMethods')?->asInt() ?? 0;
            $mi = $collection->get('mi')?->asFloat() ?? 0.0;

            $classes[] = [
                'name' => $collection->getName(),
                'methods' => $methods,
                'cc' => $cc,
                'mi' => round($mi, 1),
            ];
        }

        usort($classes, fn (array $a, array $b): int => $b['cc'] <=> $a['cc']);
        $top = array_slice($classes, 0, 10);

        if ([] === $top) {
            return;
        }

        $this->output->outNl();
        $this->output->outNl($this->formatter->bold('  Top 10 Classes by Complexity'));

        $table = new TerminalTable($this->output, $this->formatter);
        $table->setHeaders(['Class', 'Methods', 'CC', 'MI']);

        $table->setColumnFormatter(2, fn ($val, string $padded): string => match (true) {
            $val > 20 => $this->formatter->error($padded),
            $val > 10 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        $table->setColumnFormatter(3, fn ($val, string $padded): string => match (true) {
            $val < 50 => $this->formatter->error($padded),
            $val < 70 => $this->formatter->warning($padded),
            default => $this->formatter->success($padded),
        });

        foreach ($top as $class) {
            $name = $class['name'];
            if (mb_strlen($name) > 50) {
                $name = '...'.mb_substr($name, -47);
            }
            $table->addRow([$name, $class['methods'], $class['cc'], $class['mi']]);
        }

        $table->render();
    }

    private function renderSummary(): void
    {
        $getFloat = fn (string $key): float => $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->asFloat() ?? 0.0;

        $avgCC = round($getFloat(MetricKey::OVERALL_AVG_CC), 2);
        $avgMI = round($getFloat(MetricKey::OVERALL_AVG_MI), 1);

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
