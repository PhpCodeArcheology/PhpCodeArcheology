<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

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
                'healthScore' => [],
                'errors' => [],
                'warnings' => [],
                'techDebt' => [],
                'classes' => [],
                'loc' => [],
            ],
        ];

        $num = static fn (mixed $v): int|float => is_numeric($v) ? $v + 0 : 0;

        foreach ($runs as $run) {
            $dateRaw = $run['date'] ?? '';
            $trendData['labels'][] = substr(is_string($dateRaw) ? $dateRaw : '', 0, 10);
            $runData = $run['data'] ?? null;
            $projectDataRaw = is_array($runData) ? ($runData['ProjectCollection'] ?? null) : null;
            $projectData = is_array($projectDataRaw) ? $projectDataRaw : [];

            $trendData['datasets']['avgCC'][] = $num($projectData['overallAvgCC'] ?? null);
            $trendData['datasets']['avgMI'][] = $num($projectData['overallAvgMI'] ?? null);
            $trendData['datasets']['healthScore'][] = $num($projectData['healthScore'] ?? null);
            $trendData['datasets']['errors'][] = $num($projectData['overallErrorCount'] ?? null);
            $trendData['datasets']['warnings'][] = $num($projectData['overallWarningCount'] ?? null);
            $trendData['datasets']['techDebt'][] = $num($projectData['overallTechnicalDebtScore'] ?? null);
            $trendData['datasets']['classes'][] = $num($projectData['overallClasses'] ?? null);
            $trendData['datasets']['loc'][] = $num($projectData['overallLoc'] ?? null);
        }

        $this->templateData['trendData'] = json_encode($trendData);
        $this->templateData['hasMultipleRuns'] = count($runs) > 1;
        $this->templateData['runCount'] = count($runs);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function readAllRuns(): array
    {
        if (!isset($this->historyFile) || !file_exists($this->historyFile)) {
            return [];
        }

        $fp = @fopen($this->historyFile, 'r');
        if (false === $fp) {
            return [];
        }

        $projectMetrics = [
            'overallAvgCC', 'overallAvgMI', 'healthScore',
            'overallErrorCount', 'overallWarningCount',
            'overallTechnicalDebtScore', 'overallClasses', 'overallLoc',
        ];

        $runs = [];
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line);
            if ('' === $line) {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded) || !isset($decoded['date'])) {
                continue;
            }

            $decodedData = $decoded['data'] ?? null;
            $rawProject = is_array($decodedData) ? ($decodedData['ProjectCollection'] ?? []) : [];
            $projectData = is_array($rawProject) ? $rawProject : [];

            $slim = [];
            foreach ($projectMetrics as $metric) {
                if (isset($projectData[$metric])) {
                    $slim[$metric] = $projectData[$metric];
                }
            }

            $runs[] = ['date' => $decoded['date'], 'data' => ['ProjectCollection' => $slim]];
        }

        fclose($fp);

        return $runs;
    }
}
