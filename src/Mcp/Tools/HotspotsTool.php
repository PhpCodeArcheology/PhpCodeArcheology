<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class HotspotsTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getHotspots(int $limit = 10): string
    {
        try {
            $data = $this->factory->getGitDataProvider()->getTemplateData();
            $hotspots = $data['hotspots'] ?? [];
            $totalCommits = $data['gitTotalCommits'] ?? 0;
            $activeAuthors = $data['gitActiveAuthors'] ?? 0;
            $period = $data['gitAnalysisPeriod'] ?? 'N/A';

            if (empty($hotspots)) {
                return "No hotspot data available. Git analysis may be disabled or no git history found.";
            }

            $hotspots = array_slice($hotspots, 0, max(1, $limit));

            $lines = [
                "# Code Hotspots (Churn × Complexity)",
                "",
                "Git Analysis Period: {$period}",
                "Total Commits: {$totalCommits} | Active Authors: {$activeAuthors}",
                "",
                sprintf("%-40s %6s %4s %6s %8s %8s", "File", "Churn", "CC", "LOC", "Authors", "Score"),
                str_repeat("-", 78),
            ];

            foreach ($hotspots as $h) {
                $score = $h['churn'] * $h['cc'];
                $name = $h['name'] !== '' ? $h['name'] : basename($h['id']);
                $shortName = strlen($name) > 38 ? '...' . substr($name, -35) : $name;
                $lines[] = sprintf("%-40s %6d %4d %6d %8d %8d",
                    $shortName, $h['churn'], $h['cc'], $h['loc'], $h['authors'], $score
                );
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error retrieving hotspots: " . $e->getMessage();
        }
    }
}
