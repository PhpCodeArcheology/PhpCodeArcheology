<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Predictions\PredictionInterface;

class SummaryPrinter
{
    /**
     * @param array<int, int> $problems
     */
    public function print(MetricsController $metricsController, Config $config, array $problems, CliOutput $output, CliFormatter $formatter): void
    {
        $getInt = function (string $key) use ($metricsController): int {
            $v = $metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, $key
            )?->getValue() ?? 0;

            return is_numeric($v) ? (int) $v : 0;
        };

        $getFloat = function (string $key) use ($metricsController): float {
            $v = $metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, $key
            )?->getValue() ?? 0;

            return is_numeric($v) ? (float) $v : 0.0;
        };

        $getRaw = function (string $key) use ($metricsController): mixed {
            return $metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, $key
            )?->getValue() ?? null;
        };

        $files = number_format($getInt('overallFiles'));
        $classes = number_format($getInt('overallClasses'));
        $lloc = number_format($getInt('overallLloc'));
        $avgCC = round($getFloat('overallAvgCC'), 2);
        $avgMI = round($getFloat('overallAvgMI'), 1);
        $healthScore = round($getFloat('healthScore'), 1);
        $gradeRaw = $getRaw('healthScoreGrade');
        $grade = is_string($gradeRaw) ? $gradeRaw : '?';

        $errors = $problems[PredictionInterface::ERROR] ?? 0;
        $warnings = $problems[PredictionInterface::WARNING] ?? 0;
        $infos = $problems[PredictionInterface::INFO] ?? 0;

        $line = str_repeat("\u{2550}", 50);

        $errStr = $errors > 0 ? $formatter->error(number_format($errors)) : $formatter->success('0');
        $warnStr = $warnings > 0 ? $formatter->warning(number_format($warnings)) : $formatter->success('0');
        $infoStr = number_format($infos);

        $reportDirRaw = $config->get('reportDir');
        $reportDir = is_string($reportDirRaw) ? $reportDirRaw : '';
        $reportTypeRaw = $config->get('reportType');
        $reportType = is_string($reportTypeRaw) ? $reportTypeRaw : 'html';
        $reportFile = match ($reportType) {
            'html' => $reportDir.'/html/index.html',
            'json' => $reportDir.'/json/report.json',
            'sarif' => $reportDir.'/sarif/report.sarif.json',
            'ai-summary' => $reportDir.'/ai-summary/ai-summary.md',
            'markdown' => $reportDir.'/markdown/index.md',
            'graph' => $reportDir.'/graph/graph.json',
            default => $reportDir,
        };

        $output->outNl($line);
        $output->outNl(sprintf(
            ' Files: %s  |  Classes: %s  |  LLOC: %s',
            $formatter->info($files),
            $formatter->info($classes),
            $formatter->info($lloc),
        ));
        $output->outNl(sprintf(
            ' Avg CC: %s  |  Avg MI: %s  |  Health: %s (%s)',
            $formatter->info((string) $avgCC),
            $formatter->info((string) $avgMI),
            $formatter->bold($grade),
            $formatter->info((string) $healthScore),
        ));
        $frameworksRaw = $getRaw('detectedFrameworks');
        $frameworks = is_string($frameworksRaw) ? $frameworksRaw : '';
        if ('' !== $frameworks) {
            $output->outNl(' Frameworks: '.$formatter->info($frameworks));
        }
        $output->outNl(sprintf(
            ' Problems: %s errors  |  %s warnings  |  %s info',
            $errStr,
            $warnStr,
            $infoStr,
        ));
        $output->outNl('           See report for per-file, per-class, and per-function details.');
        $output->outNl(' Report: '.$formatter->dim($reportFile));
        $output->outNl($line);
        $output->outNl();
    }
}
