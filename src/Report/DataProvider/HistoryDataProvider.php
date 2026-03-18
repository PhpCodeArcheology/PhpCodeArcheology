<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsController;

class HistoryDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private string $historyFile;

    public function setHistoryFile(string $historyFile): void
    {
        $this->historyFile = $historyFile;
        $this->gatherData();
    }

    public function gatherData(): void
    {
        $runs = $this->readAllRuns();

        $trendData = [
            'labels' => [],
            'datasets' => [
                'avgCC' => [],
                'avgMI' => [],
                'errors' => [],
                'warnings' => [],
                'classes' => [],
                'loc' => [],
            ],
        ];

        foreach ($runs as $run) {
            $trendData['labels'][] = substr($run->date, 0, 10);
            $projectData = $run->data->ProjectCollection ?? null;

            $trendData['datasets']['avgCC'][] = $projectData->overallAvgCC ?? 0;
            $trendData['datasets']['avgMI'][] = $projectData->overallAvgMI ?? 0;
            $trendData['datasets']['errors'][] = $projectData->overallErrorCount ?? 0;
            $trendData['datasets']['warnings'][] = $projectData->overallWarningCount ?? 0;
            $trendData['datasets']['classes'][] = $projectData->overallClasses ?? 0;
            $trendData['datasets']['loc'][] = $projectData->overallLoc ?? 0;
        }

        $this->templateData['trendData'] = json_encode($trendData);
        $this->templateData['hasMultipleRuns'] = count($runs) > 1;
        $this->templateData['runCount'] = count($runs);
    }

    private function readAllRuns(): array
    {
        if (!isset($this->historyFile) || !file_exists($this->historyFile)) {
            return [];
        }

        $lines = @file($this->historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $runs = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line);
            if ($decoded !== null && isset($decoded->date)) {
                $runs[] = $decoded;
            }
        }

        return $runs;
    }
}
