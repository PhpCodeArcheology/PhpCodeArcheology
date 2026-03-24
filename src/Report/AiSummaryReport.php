<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AiSummaryReport implements ReportInterface
{
    private string $outputDir;

    public function __construct(
        private readonly Config              $config,
        private readonly DataProviderFactory $dataProviderFactory,
        private readonly false|\DateTimeImmutable $historyDate,
        protected readonly FilesystemLoader  $twigLoader,
        protected readonly Environment       $twig,
        private readonly CliOutput           $output)
    {
        $this->outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR . 'ai-summary' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }
    }

    public function generate(): void
    {
        $this->output->outWithMemory('Generating AI summary...');

        $projectData = $this->dataProviderFactory->getProjectDataProvider()->getTemplateData();
        $problemData = $this->dataProviderFactory->getProblemDataProvider()->getTemplateData();
        $gitData = $this->dataProviderFactory->getGitDataProvider()->getTemplateData();

        $lines = [];

        $lines[] = '# Code Analysis Summary';
        $lines[] = '';
        $lines[] = $this->buildExecutiveSummary($projectData);
        $lines[] = $this->buildTopProblems($problemData);
        $lines[] = $this->buildMetricsOverview($projectData);
        $lines[] = $this->buildHotspots($gitData);

        $refactoringData = $this->dataProviderFactory->getRefactoringPriorityDataProvider()->getTemplateData();
        $lines[] = $this->buildRefactoringPriorities($refactoringData);

        $content = implode("\n", $lines);
        file_put_contents($this->outputDir . 'ai-summary.md', $content);

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl($formatter->success('AI summary written to ai-summary.md'));
        $this->output->outNl();
    }

    private function buildExecutiveSummary(array $projectData): string
    {
        $metrics = $projectData['elements'] ?? [];
        $lines = [];

        $lines[] = '## Executive Summary';
        $lines[] = '';

        $keyMetrics = [
            'overallFiles' => 'Files',
            'overallClasses' => 'Classes',
            'overallFunctionCount' => 'Functions',
            'overallMethodsCount' => 'Methods',
            'overallLoc' => 'LOC',
            'overallLloc' => 'Logical LOC',
            'overallAvgCC' => 'Avg Cyclomatic Complexity',
            'overallAvgMI' => 'Avg Maintainability Index',
            'healthScore' => 'Health Score',
            'overallTechnicalDebtScore' => 'Technical Debt Score',
            'overallErrorCount' => 'Errors',
            'overallWarningCount' => 'Warnings',
            'overallInformationCount' => 'Info',
        ];

        foreach ($keyMetrics as $key => $label) {
            if (isset($metrics[$key]) && $metrics[$key] instanceof MetricValue) {
                $lines[] = '- ' . $label . ': ' . $metrics[$key]->getValueFormatted();
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function buildTopProblems(array $problemData): string
    {
        $lines = [];
        $lines[] = '## Top Problems';
        $lines[] = '';

        $allProblems = [];

        $categoryMap = [
            'fileProblems' => 'file',
            'classProblems' => 'class',
            'functionProblems' => 'function',
        ];

        foreach ($categoryMap as $key => $category) {
            foreach ($problemData[$key] ?? [] as $entityId => $entityProblems) {
                $name = $entityId;
                $data = $entityProblems['data'] ?? null;
                if ($data !== null && method_exists($data, 'getName')) {
                    $name = $data->getName();
                }

                foreach ($entityProblems['problems'] ?? [] as $problem) {
                    $allProblems[] = [
                        'level' => $problem->getProblemLevel(),
                        'category' => $category,
                        'entity' => $name,
                        'message' => $problem->getMessage(),
                        'recommendation' => $problem->getRecommendation(),
                    ];
                }
            }
        }

        // Sort by level descending (errors first)
        usort($allProblems, fn($a, $b) => $b['level'] <=> $a['level']);

        // Top 10
        $top = array_slice($allProblems, 0, 10);

        if (empty($top)) {
            $lines[] = 'No problems detected.';
            $lines[] = '';
            return implode("\n", $lines);
        }

        foreach ($top as $i => $problem) {
            $levelStr = match ($problem['level']) {
                PredictionInterface::ERROR => 'ERROR',
                PredictionInterface::WARNING => 'WARNING',
                PredictionInterface::INFO => 'INFO',
                default => 'UNKNOWN',
            };

            $lines[] = ($i + 1) . '. [' . $levelStr . '] ' . $problem['category'] . ':' . $problem['entity'];
            $lines[] = '   ' . $problem['message'];
            if ($problem['recommendation'] !== '') {
                $lines[] = '   Recommendation: ' . $problem['recommendation'];
            }
        }

        $remaining = count($allProblems) - 10;
        if ($remaining > 0) {
            $lines[] = '';
            $lines[] = '... and ' . $remaining . ' more problems.';
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function buildMetricsOverview(array $projectData): string
    {
        $metrics = $projectData['elements'] ?? [];
        $lines = [];

        $lines[] = '## Metrics Overview';
        $lines[] = '';

        foreach ($metrics as $key => $metricValue) {
            if (!$metricValue instanceof MetricValue) {
                continue;
            }

            $type = $metricValue->getMetricType();
            $lines[] = '- ' . $type->getName() . ' (' . $key . '): ' . $metricValue->getValueFormatted();
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function buildHotspots(array $gitData): string
    {
        $lines = [];
        $lines[] = '## Hotspots';
        $lines[] = '';

        $hotspots = array_slice($gitData['hotspots'] ?? [], 0, 10);

        if (empty($hotspots)) {
            $lines[] = 'No hotspot data available.';
            $lines[] = '';
            return implode("\n", $lines);
        }

        foreach ($hotspots as $i => $hotspot) {
            $score = $hotspot['churn'] * $hotspot['cc'];
            $lines[] = ($i + 1) . '. ' . $hotspot['name'] . ' (score:' . $score . ' churn:' . $hotspot['churn'] . ' cc:' . $hotspot['cc'] . ' authors:' . $hotspot['authors'] . ')';
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function buildRefactoringPriorities(array $refactoringData): string
    {
        $lines = [];
        $lines[] = '## Refactoring Priorities';
        $lines[] = '';

        $priorities = array_slice($refactoringData['refactoringPriorities'] ?? [], 0, 10);

        if (empty($priorities)) {
            $lines[] = 'No refactoring priorities detected. All classes are clean.';
            $lines[] = '';
            return implode("\n", $lines);
        }

        $lines[] = 'Classes ranked by refactoring urgency (score 0-100, higher = more urgent):';
        $lines[] = '';

        foreach ($priorities as $i => $entry) {
            $drivers = implode(', ', $entry['drivers'] ?? []);
            $lines[] = ($i + 1) . '. **' . $entry['fullName'] . '** (score:' . $entry['score'] . ' cc:' . $entry['cc'] . ' lcom:' . $entry['lcom'] . ' lloc:' . $entry['lloc'] . ')';
            if (!empty($entry['recommendation'])) {
                $lines[] = '   ' . $entry['recommendation'];
            }
            if ($drivers !== '') {
                $lines[] = '   Drivers: ' . $drivers;
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }
}
