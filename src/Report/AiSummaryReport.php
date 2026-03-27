<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AiSummaryReport implements ReportInterface
{
    private readonly string $outputDir;

    public function __construct(
        Config $config,
        private readonly DataProviderFactory $dataProviderFactory,
        false|\DateTimeImmutable $historyDate,
        FilesystemLoader $twigLoader,
        Environment $twig,
        private readonly CliOutput $output)
    {
        $reportDir = $config->get('reportDir');
        $this->outputDir = (is_string($reportDir) ? $reportDir : '').DIRECTORY_SEPARATOR.'ai-summary'.DIRECTORY_SEPARATOR;

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
        file_put_contents($this->outputDir.'ai-summary.md', $content);

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl($formatter->success('AI summary written to ai-summary.md'));
        $this->output->outNl();
    }

    /** @param array<string, mixed> $projectData */
    private function buildExecutiveSummary(array $projectData): string
    {
        $elementsRaw = $projectData['elements'] ?? null;
        $metrics = is_array($elementsRaw) ? $elementsRaw : [];
        $lines = [];

        $lines[] = '## Executive Summary';
        $lines[] = '';

        $keyMetrics = [
            MetricKey::OVERALL_FILES => 'Files',
            MetricKey::OVERALL_CLASSES => 'Classes',
            MetricKey::OVERALL_FUNCTION_COUNT => 'Functions',
            MetricKey::OVERALL_METHODS_COUNT => 'Methods',
            MetricKey::OVERALL_LOC => 'LOC',
            MetricKey::OVERALL_LLOC => 'Logical LOC',
            MetricKey::OVERALL_AVG_CC => 'Avg Cyclomatic Complexity',
            MetricKey::OVERALL_AVG_MI => 'Avg Maintainability Index',
            MetricKey::HEALTH_SCORE => 'Health Score',
            MetricKey::OVERALL_TECHNICAL_DEBT_SCORE => 'Technical Debt Score',
            MetricKey::OVERALL_ERROR_COUNT => 'Errors',
            MetricKey::OVERALL_WARNING_COUNT => 'Warnings',
            MetricKey::OVERALL_INFORMATION_COUNT => 'Info',
        ];

        foreach ($keyMetrics as $key => $label) {
            $metricValue = $metrics[$key] ?? null;
            if ($metricValue instanceof MetricValue) {
                $formatted = $metricValue->getValueFormatted();
                $lines[] = '- '.$label.': '.(is_scalar($formatted) ? (string) $formatted : '');
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $problemData */
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
            $categoryData = $problemData[$key] ?? null;
            foreach (is_array($categoryData) ? $categoryData : [] as $entityId => $entityProblems) {
                if (!is_array($entityProblems)) {
                    continue;
                }
                $name = (string) $entityId;
                $data = $entityProblems['data'] ?? null;
                if (is_object($data) && method_exists($data, 'getName')) {
                    $nameResult = $data->getName();
                    if (is_string($nameResult)) {
                        $name = $nameResult;
                    }
                }

                $problemList = $entityProblems['problems'] ?? null;
                foreach (is_array($problemList) ? $problemList : [] as $problem) {
                    if (!$problem instanceof ProblemInterface) {
                        continue;
                    }
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
        usort($allProblems, fn (array $a, array $b): int => $b['level'] <=> $a['level']);

        // Top 10
        $top = array_slice($allProblems, 0, 10);

        if ([] === $top) {
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

            $lines[] = ((int) $i + 1).'. ['.$levelStr.'] '.$problem['category'].':'.$problem['entity'];
            $lines[] = '   '.$problem['message'];
            if ('' !== $problem['recommendation']) {
                $lines[] = '   Recommendation: '.$problem['recommendation'];
            }
        }

        $remaining = count($allProblems) - 10;
        if ($remaining > 0) {
            $lines[] = '';
            $lines[] = '... and '.$remaining.' more problems.';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $projectData */
    private function buildMetricsOverview(array $projectData): string
    {
        $elementsRaw = $projectData['elements'] ?? null;
        $metrics = is_array($elementsRaw) ? $elementsRaw : [];
        $lines = [];

        $lines[] = '## Metrics Overview';
        $lines[] = '';

        foreach ($metrics as $key => $metricValue) {
            if (!$metricValue instanceof MetricValue) {
                continue;
            }

            $type = $metricValue->getMetricType();
            $formatted = $metricValue->getValueFormatted();
            $lines[] = '- '.$type->getName().' ('.$key.'): '.(is_scalar($formatted) ? (string) $formatted : '');
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $gitData */
    private function buildHotspots(array $gitData): string
    {
        $lines = [];
        $lines[] = '## Hotspots';
        $lines[] = '';

        $hotspotsRaw = $gitData['hotspots'] ?? null;
        $hotspots = array_slice(is_array($hotspotsRaw) ? $hotspotsRaw : [], 0, 10);

        if ([] === $hotspots) {
            $lines[] = 'No hotspot data available.';
            $lines[] = '';

            return implode("\n", $lines);
        }

        foreach ($hotspots as $i => $hotspot) {
            if (!is_array($hotspot)) {
                continue;
            }
            $churnRaw = $hotspot['churn'] ?? 0;
            $ccRaw = $hotspot['cc'] ?? 0;
            $churn = is_numeric($churnRaw) ? (float) $churnRaw : 0.0;
            $cc = is_numeric($ccRaw) ? (float) $ccRaw : 0.0;
            $score = $churn * $cc;
            $name = is_string($hotspot['name'] ?? null) ? $hotspot['name'] : '';
            $authorsRaw = $hotspot['authors'] ?? '';
            $authors = is_scalar($authorsRaw) ? (string) $authorsRaw : '';
            $lines[] = ($i + 1).'. '.$name.' (score:'.$score.' churn:'.$churn.' cc:'.$cc.' authors:'.$authors.')';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $refactoringData */
    private function buildRefactoringPriorities(array $refactoringData): string
    {
        $lines = [];
        $lines[] = '## Refactoring Priorities';
        $lines[] = '';

        $prioritiesRaw = $refactoringData['refactoringPriorities'] ?? null;
        $priorities = array_slice(is_array($prioritiesRaw) ? $prioritiesRaw : [], 0, 10);

        if ([] === $priorities) {
            $lines[] = 'No refactoring priorities detected. All classes are clean.';
            $lines[] = '';

            return implode("\n", $lines);
        }

        $lines[] = 'Classes ranked by refactoring urgency (score 0-100, higher = more urgent):';
        $lines[] = '';

        foreach ($priorities as $i => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $driversRaw = $entry['drivers'] ?? null;
            $drivers = is_array($driversRaw)
                ? implode(', ', array_map(fn (mixed $d): string => is_string($d) ? $d : '', $driversRaw))
                : '';
            $fullName = is_string($entry['fullName'] ?? null) ? $entry['fullName'] : '';
            $score = is_scalar($entry['score'] ?? null) ? $entry['score'] : 0;
            $cc = is_scalar($entry['cc'] ?? null) ? $entry['cc'] : 0;
            $lcom = is_scalar($entry['lcom'] ?? null) ? $entry['lcom'] : 0;
            $lloc = is_scalar($entry['lloc'] ?? null) ? $entry['lloc'] : 0;
            $recommendation = is_string($entry['recommendation'] ?? null) ? $entry['recommendation'] : '';
            $lines[] = ($i + 1).'. **'.$fullName.'** (score:'.$score.' cc:'.$cc.' lcom:'.$lcom.' lloc:'.$lloc.')';
            if ('' !== $recommendation) {
                $lines[] = '   '.$recommendation;
            }
            if ('' !== $drivers) {
                $lines[] = '   Drivers: '.$drivers;
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
