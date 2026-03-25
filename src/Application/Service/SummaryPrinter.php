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
    public function print(MetricsController $metricsController, Config $config, array $problems, CliOutput $output, CliFormatter $formatter): void
    {
        $get = fn(string $key) => $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->getValue() ?? 0;

        $files = number_format((int) $get('overallFiles'));
        $classes = number_format((int) $get('overallClasses'));
        $lloc = number_format((int) $get('overallLloc'));
        $avgCC = round((float) $get('overallAvgCC'), 2);
        $avgMI = round((float) $get('overallAvgMI'), 1);
        $healthScore = round((float) $get('healthScore'), 1);
        $grade = $get('healthScoreGrade') ?: '?';

        $errors = $problems[PredictionInterface::ERROR] ?? 0;
        $warnings = $problems[PredictionInterface::WARNING] ?? 0;
        $infos = $problems[PredictionInterface::INFO] ?? 0;

        $line = str_repeat("\u{2550}", 50);

        $errStr = $errors > 0 ? $formatter->error(number_format($errors)) : $formatter->success('0');
        $warnStr = $warnings > 0 ? $formatter->warning(number_format($warnings)) : $formatter->success('0');
        $infoStr = number_format($infos);

        $reportDir = $config->get('reportDir') ?? '';
        $reportType = $config->get('reportType') ?? 'html';
        $reportFile = match ($reportType) {
            'html' => $reportDir . '/html/index.html',
            'json' => $reportDir . '/json/report.json',
            'sarif' => $reportDir . '/sarif/report.sarif.json',
            'ai-summary' => $reportDir . '/ai-summary/ai-summary.md',
            'markdown' => $reportDir . '/markdown/index.md',
            'graph' => $reportDir . '/graph/graph.json',
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
        $frameworks = $get('detectedFrameworks');
        if ($frameworks) {
            $output->outNl(' Frameworks: ' . $formatter->info($frameworks));
        }
        $output->outNl(sprintf(
            ' Errors: %s  |  Warnings: %s  |  Info: %s',
            $errStr,
            $warnStr,
            $infoStr,
        ));
        $output->outNl(' Report: ' . $formatter->dim($reportFile));
        $output->outNl($line);
        $output->outNl();
    }
}
