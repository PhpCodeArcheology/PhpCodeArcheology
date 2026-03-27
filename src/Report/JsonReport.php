<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class JsonReport implements ReportInterface
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
        $this->outputDir = (is_string($reportDir) ? $reportDir : '').DIRECTORY_SEPARATOR.'json'.DIRECTORY_SEPARATOR;

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
        file_put_contents($this->outputDir.'report.json', $json);

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl($formatter->success('JSON report written to report.json'));
        $this->output->outNl();
    }

    /**
     * @param array<string, mixed> $projectData
     *
     * @return array<string, mixed>
     */
    private function buildProjectSection(array $projectData): array
    {
        $project = [
            'commonPath' => $projectData['commonPath'] ?? '',
        ];

        $elements = $projectData['elements'] ?? null;
        foreach (is_array($elements) ? $elements : [] as $key => $metricValue) {
            if ($metricValue instanceof MetricValue) {
                $project['metrics'][(string) $key] = [
                    'value' => $metricValue->getValueFormatted(),
                    'name' => $metricValue->getMetricType()->getName(),
                ];
            }
        }

        return $project;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildFilesSection(): array
    {
        $filesData = $this->dataProviderFactory->getFilesDataProvider()->getTemplateData();
        $files = [];

        $filesRaw = $filesData['files'] ?? null;
        foreach (is_array($filesRaw) ? $filesRaw : [] as $fileKey => $fileCollection) {
            $files[] = $this->collectionToArray((string) $fileKey, $fileCollection);
        }

        return $files;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildClassesSection(): array
    {
        $classData = $this->dataProviderFactory->getClassDataProvider()->getTemplateData();
        $classes = [];

        $classesRaw = $classData['classes'] ?? null;
        foreach (is_array($classesRaw) ? $classesRaw : [] as $classKey => $classCollection) {
            $classes[] = $this->collectionToArray((string) $classKey, $classCollection);
        }

        return $classes;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildFunctionsSection(): array
    {
        $funcData = $this->dataProviderFactory->getFunctionDataProvider()->getTemplateData();
        $functions = [];

        $functionsRaw = $funcData['functions'] ?? null;
        foreach (is_array($functionsRaw) ? $functionsRaw : [] as $funcKey => $funcCollection) {
            $entry = $this->collectionToArray((string) $funcKey, $funcCollection);
            $entry['type'] = 'function';
            $functions[] = $entry;
        }

        $methodsRaw = $funcData['methods'] ?? null;
        foreach (is_array($methodsRaw) ? $methodsRaw : [] as $methodKey => $methodCollection) {
            $entry = $this->collectionToArray((string) $methodKey, $methodCollection);
            $entry['type'] = 'method';
            $functions[] = $entry;
        }

        return $functions;
    }

    /**
     * @param array<string, mixed> $problemData
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProblemsSection(array $problemData): array
    {
        $problems = [];

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
                $problemList = $entityProblems['problems'] ?? null;
                foreach (is_array($problemList) ? $problemList : [] as $problem) {
                    if (!$problem instanceof ProblemInterface) {
                        continue;
                    }
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

    /**
     * @param array<string, mixed> $gitData
     *
     * @return array<string, mixed>
     */
    private function buildGitSection(array $gitData): array
    {
        $hotspotsRaw = $gitData['hotspots'] ?? null;

        return [
            'totalCommits' => $gitData['gitTotalCommits'] ?? 0,
            'activeAuthors' => $gitData['gitActiveAuthors'] ?? 0,
            'analysisPeriod' => $gitData['gitAnalysisPeriod'] ?? 'N/A',
            'hotspots' => array_slice(is_array($hotspotsRaw) ? $hotspotsRaw : [], 0, 50),
        ];
    }

    /**
     * @param array<string, mixed> $testsData
     *
     * @return array<string, mixed>
     */
    private function buildTestsSection(array $testsData): array
    {
        $statsRaw = $testsData['stats'] ?? null;
        $stats = is_array($statsRaw) ? $statsRaw : [];

        $coverageGapsRaw = $testsData['coverageGaps'] ?? null;

        return [
            'testRatio' => $stats['testRatio'] ?? 0,
            'testFileCount' => $stats['testFileCount'] ?? 0,
            'productionFileCount' => $stats['productionFileCount'] ?? 0,
            'testedClassCount' => $stats['testedClassCount'] ?? 0,
            'untestedClassCount' => $stats['untestedClassCount'] ?? 0,
            'testedClassRatio' => $stats['testedClassRatio'] ?? 0,
            'coveragePercent' => $stats['overallCoveragePercent'] ?? null,
            'detectedTestFrameworks' => $stats['detectedTestFrameworks'] ?? '',
            'coverageGaps' => is_array($coverageGapsRaw) ? $coverageGapsRaw : [],
        ];
    }

    /** @return array<string, mixed> */
    private function collectionToArray(string $key, mixed $collection): array
    {
        if (!is_object($collection)) {
            return ['id' => $key, 'name' => $key, 'metrics' => []];
        }

        $entry = [
            'id' => $key,
            'name' => $collection instanceof MetricsCollectionInterface ? $collection->getName() : $key,
            'metrics' => [],
        ];

        if ($collection instanceof MetricsCollectionInterface) {
            foreach ($collection->getAll() as $metricKey => $metricValue) {
                $entry['metrics'][$metricKey] = $metricValue->getValueFormatted();
            }
        }

        return $entry;
    }
}
