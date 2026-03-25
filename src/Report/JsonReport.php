<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class JsonReport implements ReportInterface
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
        $this->outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }
    }

    public function generate(): void
    {
        $this->output->outWithMemory('Generating JSON report...');

        $projectData = $this->dataProviderFactory->getProjectDataProvider()->getTemplateData();
        $problemData = $this->dataProviderFactory->getProblemDataProvider()->getTemplateData();
        $gitData = $this->dataProviderFactory->getGitDataProvider()->getTemplateData();
        $testsData = $this->dataProviderFactory->getTestsDataProvider()->getTemplateData();

        $report = [
            'version' => '1.0',
            'generatedAt' => $projectData['createDate'],
            'toolVersion' => $projectData['version'],
            'project' => $this->buildProjectSection($projectData),
            'files' => $this->buildFilesSection(),
            'classes' => $this->buildClassesSection(),
            'functions' => $this->buildFunctionsSection(),
            'problems' => $this->buildProblemsSection($problemData),
            'git' => $this->buildGitSection($gitData),
            'tests' => $this->buildTestsSection($testsData),
        ];

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->outputDir . 'report.json', $json);

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl($formatter->success('JSON report written to report.json'));
        $this->output->outNl();
    }

    private function buildProjectSection(array $projectData): array
    {
        $project = [
            'commonPath' => $projectData['commonPath'] ?? '',
        ];

        foreach ($projectData['elements'] ?? [] as $key => $metricValue) {
            if ($metricValue instanceof MetricValue) {
                $project['metrics'][$key] = [
                    'value' => $metricValue->getValueFormatted(),
                    'name' => $metricValue->getMetricType()->getName(),
                ];
            }
        }

        return $project;
    }

    private function buildFilesSection(): array
    {
        $filesData = $this->dataProviderFactory->getFilesDataProvider()->getTemplateData();
        $files = [];

        foreach ($filesData['files'] ?? [] as $fileKey => $fileCollection) {
            $files[] = $this->collectionToArray($fileKey, $fileCollection);
        }

        return $files;
    }

    private function buildClassesSection(): array
    {
        $classData = $this->dataProviderFactory->getClassDataProvider()->getTemplateData();
        $classes = [];

        foreach ($classData['classes'] ?? [] as $classKey => $classCollection) {
            $classes[] = $this->collectionToArray($classKey, $classCollection);
        }

        return $classes;
    }

    private function buildFunctionsSection(): array
    {
        $funcData = $this->dataProviderFactory->getFunctionDataProvider()->getTemplateData();
        $functions = [];

        foreach ($funcData['functions'] ?? [] as $funcKey => $funcCollection) {
            $entry = $this->collectionToArray($funcKey, $funcCollection);
            $entry['type'] = 'function';
            $functions[] = $entry;
        }

        foreach ($funcData['methods'] ?? [] as $methodKey => $methodCollection) {
            $entry = $this->collectionToArray($methodKey, $methodCollection);
            $entry['type'] = 'method';
            $functions[] = $entry;
        }

        return $functions;
    }

    private function buildProblemsSection(array $problemData): array
    {
        $problems = [];

        $categoryMap = [
            'fileProblems' => 'file',
            'classProblems' => 'class',
            'functionProblems' => 'function',
        ];

        foreach ($categoryMap as $key => $category) {
            foreach ($problemData[$key] ?? [] as $entityId => $entityProblems) {
                foreach ($entityProblems['problems'] ?? [] as $problem) {
                    $problems[] = [
                        'category' => $category,
                        'entityId' => $entityId,
                        'level' => match ($problem->getProblemLevel()) {
                            PredictionInterface::ERROR => 'error',
                            PredictionInterface::WARNING => 'warning',
                            PredictionInterface::INFO => 'info',
                            default => 'unknown',
                        },
                        'message' => $problem->getMessage(),
                        'recommendation' => $problem->getRecommendation(),
                        'confidence' => $problem->getConfidence(),
                    ];
                }
            }
        }

        return $problems;
    }

    private function buildGitSection(array $gitData): array
    {
        return [
            'totalCommits' => $gitData['gitTotalCommits'] ?? 0,
            'activeAuthors' => $gitData['gitActiveAuthors'] ?? 0,
            'analysisPeriod' => $gitData['gitAnalysisPeriod'] ?? 'N/A',
            'hotspots' => array_slice($gitData['hotspots'] ?? [], 0, 50),
        ];
    }

    private function buildTestsSection(array $testsData): array
    {
        $stats = $testsData['stats'] ?? [];
        return [
            'testRatio' => $stats['testRatio'] ?? 0,
            'testFileCount' => $stats['testFileCount'] ?? 0,
            'productionFileCount' => $stats['productionFileCount'] ?? 0,
            'testedClassCount' => $stats['testedClassCount'] ?? 0,
            'untestedClassCount' => $stats['untestedClassCount'] ?? 0,
            'testedClassRatio' => $stats['testedClassRatio'] ?? 0,
            'coveragePercent' => $stats['overallCoveragePercent'] ?? null,
            'detectedTestFrameworks' => $stats['detectedTestFrameworks'] ?? '',
            'coverageGaps' => $testsData['coverageGaps'] ?? [],
        ];
    }

    private function collectionToArray(string $key, mixed $collection): array
    {
        $entry = [
            'id' => $key,
            'name' => method_exists($collection, 'getName') ? $collection->getName() : $key,
            'metrics' => [],
        ];

        if (method_exists($collection, 'getAll')) {
            foreach ($collection->getAll() as $metricKey => $metricValue) {
                if (!$metricValue instanceof MetricValue) {
                    continue;
                }

                $entry['metrics'][$metricKey] = $metricValue->getValueFormatted();
            }
        }

        return $entry;
    }
}
