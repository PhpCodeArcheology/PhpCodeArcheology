<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ProgressBar;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\Helper\FileCopier;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class HtmlReport implements ReportInterface
{
    use ReportTrait;

    private string $assetDir;

    public function __construct(
        private readonly Config             $config,
        private readonly DataProviderFactory  $dataProviderFactory,
        private readonly false|\DateTimeImmutable $historyDate,
        protected readonly FilesystemLoader $twigLoader,
        protected readonly Environment      $twig,
        private readonly CliOutput          $output)
    {
        $this->reportSubDirName = 'html';
        $this->outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR;

        $this->templateDir = dirname(__DIR__, 2) . '/templates/html' . DIRECTORY_SEPARATOR;
        $this->assetDir = $this->templateDir . 'assets';

        $this->twigLoader->setPaths($this->templateDir);
        $this->twigLoader->addPath($this->templateDir . 'parts', 'Parts');
        $this->twig->setCache(false);
    }

    public function generate(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->clearReportDir();

        mkdir(directory: $this->outputDir . 'assets', recursive: true);
        mkdir($this->outputDir . 'files');
        mkdir($this->outputDir . 'classes');
        mkdir($this->outputDir . 'functions');
        mkdir($this->outputDir . 'methods');

        $fc = new FileCopier();
        $fc->setFiles([
            $this->templateDir . 'assets',
        ]);
        $fc->copyFilesTo(rtrim($this->outputDir . '/assets', DIRECTORY_SEPARATOR));

        $this->generateReportFiles();
    }

    protected function generateReportFiles(): void
    {
        $createMethods = [
            [$this, 'generateIndexPage'],
            [$this, 'generateFilePage'],
            [$this, 'generateClassPage'],
            [$this, 'generateClassCouplingPage'],
            [$this, 'generateClassChartPage'],
            [$this, 'generatePackagesPage'],
            [$this, 'generateFunctionPage'],
            [$this, 'generateProblemsPage'],
            [$this, 'generateRefactoringRoadmapPage'],
            [$this, 'generateGitPage'],
            [$this, 'generateKnowledgeGraphPage'],
            [$this, 'generateGlossaryPage'],
        ];

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $progressBar = new ProgressBar($this->output, $formatter, count($createMethods), 'Generating report');

        foreach ($createMethods as $method) {
            $progressBar->advance();
            call_user_func($method);
        }

        $progressBar->finish();
        $this->output->outNl($formatter->success('Report ready.'));
        $this->output->outNl();
    }

    /**
     * @param mixed $functionData
     * @param string $folder
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function createFunctionFile(mixed $functionData, string $folder): void
    {
        $outputFile = $folder . '/' . $functionData['function']->getIdentifier() . '.html';

        $functionData['pageTitle'] = $functionData['isMethod'] ? 'Method metrics' : 'Function metrics';
        $functionData['currentPage'] = $outputFile;
        $functionData['isSubdir'] = true;

        $this->renderTemplate('single-function.html.twig', $functionData, $outputFile);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function generateIndexPage(): void
    {
        $templateData = $this->dataProviderFactory->getProjectDataProvider();
        $data = $templateData->getTemplateData();

        // Add problem data for dashboard
        $problemData = $this->dataProviderFactory->getProblemDataProvider()->getTemplateData();
        $data['fileProblems'] = $problemData['fileProblems'] ?? [];
        $data['classProblems'] = $problemData['classProblems'] ?? [];
        $data['functionProblems'] = $problemData['functionProblems'] ?? [];

        // Add refactoring priorities for dashboard
        $refactoringData = $this->dataProviderFactory->getRefactoringPriorityDataProvider()->getTemplateData();
        $data['topRefactoringPriorities'] = array_slice($refactoringData['refactoringPriorities'] ?? [], 0, 5);

        // Add trend data from history
        $historyFile = $this->config->get('reportDir') . DIRECTORY_SEPARATOR . 'history.jsonl';
        $historyData = $this->dataProviderFactory->getHistoryDataProvider($historyFile)->getTemplateData();
        $data['trendData'] = $historyData['trendData'] ?? '{}';
        $data['hasMultipleRuns'] = $historyData['hasMultipleRuns'] ?? false;
        $data['runCount'] = $historyData['runCount'] ?? 0;

        // Knowledge Graph stats for dashboard widget
        $graphProvider = $this->dataProviderFactory->getGraphDataProvider();
        $graphTemplateData = $graphProvider->getTemplateData();
        $graphData = $graphTemplateData['graphData'] ?? ['nodes' => [], 'edges' => [], 'cycles' => []];

        $data['kgNodeCount'] = count($graphData['nodes']);
        $data['kgEdgeCount'] = count($graphData['edges']);
        $data['kgCycleCount'] = count($graphData['cycles']);
        $data['kgPackageCount'] = count(array_filter($graphData['nodes'], fn(array $n) => $n['type'] === 'package'));

        // Top 5 most-connected classes
        $classNodes = array_filter($graphData['nodes'], fn(array $n) => $n['type'] === 'class');
        $classConnections = [];
        foreach ($classNodes as $node) {
            $connections = ($node['metrics']['afferentCoupling'] ?? 0) + ($node['metrics']['efferentCoupling'] ?? 0);
            $classConnections[] = [
                'id' => str_replace('class:', '', $node['id']),
                'name' => $node['name'],
                'connections' => $connections,
            ];
        }
        usort($classConnections, fn($a, $b) => $b['connections'] - $a['connections']);
        $data['kgTopConnected'] = array_slice($classConnections, 0, 5);

        $data['pageTitle'] = 'Dashboard';
        $data['currentPage'] = 'index.html';

        $this->renderTemplate('index.html.twig', $data, 'index.html');
    }

    protected function generateFilePage(): void
    {
        $data = $this->dataProviderFactory->getFilesDataProvider()->getTemplateData();

        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'files.html';
        $this->renderTemplate('files.html.twig', $data, 'files.html');

        $files = $data['files'];
        unset($data['files']);

        foreach ($files as $fileKey => $fileData) {
            $outputFile = 'files/' . $fileKey . '.html';

            $templateData = $data;
            $templateData['file'] = $fileData;
            $templateData['pageTitle'] = 'File metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-file.html.twig', $templateData, $outputFile);
        }
        unset($data, $files);
    }

    protected function generateClassPage(): void
    {
        $data = $this->dataProviderFactory->getClassDataProvider()->getTemplateData();

        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');

        $classes = $data['classes'];
        unset($data['classes']);

        foreach ($classes as $classKey => $classData) {
            $outputFile = 'classes/' . $classKey . '.html';

            $templateData = $data;
            $templateData['class'] = $classData;
            $templateData['pageTitle'] = 'Class metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-class.html.twig', $templateData, $outputFile);
        }
        unset($data, $classes);
    }

    protected function generateFunctionPage(): void
    {
        $data = $this->dataProviderFactory->getFunctionDataProvider()->getTemplateData();

        $data['pageTitle'] = 'Function metrics';
        $data['currentPage'] = 'functions-list.html';
        $this->renderTemplate('functions-list.html.twig', $data, 'functions-list.html');

        $functions = $data['functions'];
        $methods = $data['methods'];
        unset($data['functions'], $data['methods']);

        foreach ($functions as $functionData) {
            $templateData = $data;
            $templateData['isMethod'] = false;
            $templateData['function'] = $functionData;
            $this->createFunctionFile($templateData, 'functions');
        }
        unset($functions);

        foreach ($methods as $functionData) {
            $templateData = $data;
            $templateData['isMethod'] = true;
            $templateData['function'] = $functionData;
            $this->createFunctionFile($templateData, 'methods');
        }
        unset($data, $methods);
    }

    protected function generatePackagesPage(): void
    {
        $templateData = $this->dataProviderFactory->getPackagDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'packages-list.html';
        $this->renderTemplate('packages-list.html.twig', $data, 'packages-list.html');
    }

    protected function generateClassCouplingPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassCouplingDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class coupling';
        $data['currentPage'] = 'class-coupling.html';
        $this->renderTemplate('class-coupling.html.twig', $data, 'class-coupling.html');
    }

    protected function generateClassChartPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassesChartDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Classes chart';
        $data['currentPage'] = 'classes-chart.html';
        $this->renderTemplate('classes-chart.html.twig', $data, 'classes-chart.html');
    }

    protected function generateProblemsPage(): void
    {
        $templateData = $this->dataProviderFactory->getProblemDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Problems';
        $data['currentPage'] = 'problems.html';

        $this->renderTemplate('problems.html.twig', $data, 'problems.html');

        $data['currentPage'] = 'file-problems.html';
        $this->renderTemplate('file-problems.html.twig', $data, 'file-problems.html');

        $data['currentPage'] = 'class-problems.html';
        $this->renderTemplate('class-problems.html.twig', $data, 'class-problems.html');

        $data['currentPage'] = 'function-problems.html';
        $this->renderTemplate('function-problems.html.twig', $data, 'function-problems.html');
    }

    protected function generateRefactoringRoadmapPage(): void
    {
        $data = $this->dataProviderFactory->getRefactoringPriorityDataProvider()->getTemplateData();
        $data['pageTitle'] = 'Refactoring Roadmap';
        $data['currentPage'] = 'refactoring-roadmap.html';
        $this->renderTemplate('refactoring-roadmap.html.twig', $data, 'refactoring-roadmap.html');
    }

    protected function generateGitPage(): void
    {
        $data = $this->dataProviderFactory->getGitDataProvider()->getTemplateData();
        $data['pageTitle'] = 'Git Analysis';
        $data['currentPage'] = 'git.html';
        $this->renderTemplate('git.html.twig', $data, 'git.html');
    }

    protected function generateKnowledgeGraphPage(): void
    {
        $graphProvider = $this->dataProviderFactory->getGraphDataProvider();
        $data = $graphProvider->getTemplateData();
        $data['graphJson'] = json_encode($data['graphData'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        unset($data['graphData']);
        $data['pageTitle'] = 'Knowledge Graph';
        $data['currentPage'] = 'knowledge-graph.html';
        $this->renderTemplate('knowledge-graph.html.twig', $data, 'knowledge-graph.html');
    }

    protected function generateGlossaryPage(): void
    {
        $data = $this->dataProviderFactory->getProjectDataProvider()->getTemplateData();

        // Build glossary from metric data files
        $glossary = [];
        $metricFiles = glob(dirname(__DIR__, 2) . '/data/metrics/*.php');
        foreach ($metricFiles as $file) {
            $metrics = require $file;
            $category = basename($file, '.php');

            foreach ($metrics as $metric) {
                if (!isset($metric['name']) || ($metric['type'] ?? '') === 'storage') {
                    continue;
                }
                if (($metric['visibility'] ?? null) === \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere) {
                    continue;
                }

                $glossary[$category][] = [
                    'key' => $metric['key'],
                    'name' => $metric['name'],
                    'description' => $metric['description'] ?? '',
                    'valueType' => ($metric['valueType'] ?? null)?->name ?? 'Unknown',
                    'better' => ($metric['better'] ?? null)?->name ?? 'Irrelevant',
                ];
            }
        }

        $data['glossary'] = $glossary;
        $data['pageTitle'] = 'Metric Glossary';
        $data['currentPage'] = 'glossary.html';
        $this->renderTemplate('glossary.html.twig', $data, 'glossary.html');
    }
}
