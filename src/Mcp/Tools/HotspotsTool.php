<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class HotspotsTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getHotspots(int $limit = 10): string
    {
        try {
            $data = $this->factory->getGitDataProvider()->getTemplateData();
            $hotspotsRaw = $data['hotspots'] ?? [];
            $hotspots = is_array($hotspotsRaw) ? $hotspotsRaw : [];
            $totalCommitsRaw = $data['gitTotalCommits'] ?? 0;
            $totalCommits = is_numeric($totalCommitsRaw) ? (int) $totalCommitsRaw : 0;
            $activeAuthorsRaw = $data['gitActiveAuthors'] ?? 0;
            $activeAuthors = is_numeric($activeAuthorsRaw) ? (int) $activeAuthorsRaw : 0;
            $periodRaw = $data['gitAnalysisPeriod'] ?? 'N/A';
            $period = is_scalar($periodRaw) ? (string) $periodRaw : 'N/A';

            if (empty($hotspots)) {
                return 'No hotspot data available. Git analysis may be disabled or no git history found.';
            }

            $hotspots = array_slice($hotspots, 0, max(1, $limit));

            $lines = [
                '# Code Hotspots (Churn × Complexity)',
                '',
                "Git Analysis Period: {$period}",
                "Total Commits: {$totalCommits} | Active Authors: {$activeAuthors}",
                '',
                sprintf('%-40s %6s %4s %6s %8s %8s', 'File', 'Churn', 'CC', 'LOC', 'Authors', 'Score'),
                str_repeat('-', 78),
            ];

            foreach ($hotspots as $h) {
                if (!is_array($h)) {
                    continue;
                }
                $churn = is_numeric($h['churn'] ?? null) ? (int) $h['churn'] : 0;
                $cc = is_numeric($h['cc'] ?? null) ? (int) $h['cc'] : 0;
                $loc = is_numeric($h['loc'] ?? null) ? (int) $h['loc'] : 0;
                $authors = is_numeric($h['authors'] ?? null) ? (int) $h['authors'] : 0;
                $score = $churn * $cc;
                $nameRaw = $h['name'] ?? null;
                $rawId = $h['id'] ?? null;
                $idStr = is_scalar($rawId) ? (string) $rawId : '';
                $name = is_scalar($nameRaw) && '' !== (string) $nameRaw ? (string) $nameRaw : basename($idStr);
                $shortName = strlen($name) > 38 ? '...'.substr($name, -35) : $name;
                $lines[] = sprintf('%-40s %6d %4d %6d %8d %8d',
                    $shortName, $churn, $cc, $loc, $authors, $score
                );
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving hotspots.';
        }
    }
}
