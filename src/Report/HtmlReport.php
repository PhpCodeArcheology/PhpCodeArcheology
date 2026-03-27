<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ProgressBar;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
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

    public function __construct(
        private readonly Config $config,
        private readonly DataProviderFactory $dataProviderFactory,
        protected readonly false|\DateTimeImmutable $historyDate,
        protected readonly FilesystemLoader $twigLoader,
        protected readonly Environment $twig,
        private readonly CliOutput $output)
    {
        $this->reportSubDirName = 'html';
        $reportDir = $config->get('reportDir');
        $this->outputDir = (is_string($reportDir) ? $reportDir : '').DIRECTORY_SEPARATOR.'html'.DIRECTORY_SEPARATOR;

        $this->templateDir = dirname(__DIR__, 2).'/templates/html'.DIRECTORY_SEPARATOR;

        $this->twigLoader->setPaths($this->templateDir);
        $this->twigLoader->addPath($this->templateDir.'parts', 'Parts');
        $this->twig->setCache(false);
    }

    public function generate(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->clearReportDir();

        mkdir(directory: $this->outputDir.'assets', recursive: true);
        mkdir($this->outputDir.'files');
        mkdir($this->outputDir.'classes');
        mkdir($this->outputDir.'functions');
        mkdir($this->outputDir.'methods');

        $fc = new FileCopier();
        $fc->setFiles([
            $this->templateDir.'assets',
        ]);
        $fc->copyFilesTo(rtrim($this->outputDir.'/assets', DIRECTORY_SEPARATOR));

        $this->generateReportFiles();
    }

    protected function generateReportFiles(): void
    {
        $createMethods = [
            $this->generateIndexPage(...),
            $this->generateFilePage(...),
            $this->generateClassPage(...),
            $this->generateClassCouplingPage(...),
            $this->generateClassChartPage(...),
            $this->generatePackagesPage(...),
            $this->generateFunctionPage(...),
            $this->generateProblemsPage(...),
            $this->generateRefactoringRoadmapPage(...),
            $this->generateGitPage(...),
            $this->generateTestsPage(...),
            $this->generateKnowledgeGraphPage(...),
            $this->generateGlossaryPage(...),
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
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    /** @param array<string, mixed> $functionData */
    public function createFunctionFile(array $functionData, string $folder): void
    {
        $function = $functionData['function'];
        $identifier = $function instanceof MetricsCollectionInterface ? $function->getIdentifier() : null;
        $outputFile = $folder.'/'.(null !== $identifier ? (string) $identifier : '').'.html';

        $functionData['pageTitle'] = ($functionData['isMethod'] ?? false) ? 'Method metrics' : 'Function metrics';
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
        $refPriorities = $refactoringData['refactoringPriorities'] ?? null;
        $data['topRefactoringPriorities'] = array_slice(is_array($refPriorities) ? $refPriorities : [], 0, 5);

        // Add trend data from history
        $reportDirVal = $this->config->get('reportDir');
        $historyFile = (is_string($reportDirVal) ? $reportDirVal : '').DIRECTORY_SEPARATOR.'history.jsonl';
        $historyData = $this->dataProviderFactory->getHistoryDataProvider($historyFile)->getTemplateData();
        $data['trendData'] = $historyData['trendData'] ?? '{}';
        $data['hasMultipleRuns'] = $historyData['hasMultipleRuns'] ?? false;
        $data['runCount'] = $historyData['runCount'] ?? 0;

        // Knowledge Graph stats for dashboard widget
        $graphProvider = $this->dataProviderFactory->getGraphDataProvider();
        $graphTemplateData = $graphProvider->getTemplateData();
        $graphDataRaw = $graphTemplateData['graphData'] ?? null;
        $graphData = is_array($graphDataRaw) ? $graphDataRaw : [];
        $graphNodes = is_array($graphData['nodes'] ?? null) ? $graphData['nodes'] : [];
        $graphEdges = is_array($graphData['edges'] ?? null) ? $graphData['edges'] : [];
        $graphCycles = is_array($graphData['cycles'] ?? null) ? $graphData['cycles'] : [];

        $data['kgNodeCount'] = count($graphNodes);
        $data['kgEdgeCount'] = count($graphEdges);
        $data['kgCycleCount'] = count($graphCycles);
        $data['kgPackageCount'] = count(array_filter($graphNodes, fn (mixed $n): bool => is_array($n) && 'package' === ($n['type'] ?? '')));

        // Count ERROR-level cycle problems (framework-pattern cycles are INFO, not counted)
        $errorCycleCount = 0;
        $classProblemsData = $data['classProblems'];
        foreach (is_array($classProblemsData) ? $classProblemsData : [] as $classProblemData) {
            $problemList = is_array($classProblemData) ? ($classProblemData['problems'] ?? null) : null;
            foreach (is_array($problemList) ? $problemList : [] as $problem) {
                if ($problem instanceof \PhpCodeArch\Predictions\Problems\DependencyCycleProblem
                    && \PhpCodeArch\Predictions\PredictionInterface::ERROR === $problem->getProblemLevel()) {
                    ++$errorCycleCount;
                }
            }
        }
        $data['errorCycleCount'] = $errorCycleCount;

        // Top 5 most-connected classes
        $classNodes = array_filter($graphNodes, fn (mixed $n): bool => is_array($n) && 'class' === ($n['type'] ?? ''));
        $classConnections = [];
        foreach ($classNodes as $node) {
            $metrics = is_array($node['metrics'] ?? null) ? $node['metrics'] : [];
            $afferent = $metrics['afferentCoupling'] ?? 0;
            $efferent = $metrics['efferentCoupling'] ?? 0;
            $connections = (is_int($afferent) ? $afferent : 0) + (is_int($efferent) ? $efferent : 0);
            $nodeId = is_string($node['id'] ?? null) ? $node['id'] : '';
            $nodeName = is_string($node['name'] ?? null) ? $node['name'] : '';
            $classConnections[] = [
                'id' => str_replace('class:', '', $nodeId),
                'name' => $nodeName,
                'connections' => $connections,
            ];
        }
        usort($classConnections, fn (array $a, array $b): int => $b['connections'] - $a['connections']);
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

        $filesData = $data['files'];
        unset($data['files']);

        foreach (is_array($filesData) ? $filesData : [] as $fileKey => $fileData) {
            $outputFile = 'files/'.$fileKey.'.html';

            $templateData = $data;
            $templateData['file'] = $fileData;
            $templateData['pageTitle'] = 'File metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-file.html.twig', $templateData, $outputFile);
        }
        unset($data, $filesData);
    }

    protected function generateClassPage(): void
    {
        $data = $this->dataProviderFactory->getClassDataProvider()->getTemplateData();

        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');

        $classesData = $data['classes'];
        unset($data['classes']);

        foreach (is_array($classesData) ? $classesData : [] as $classKey => $classData) {
            $outputFile = 'classes/'.$classKey.'.html';

            $templateData = $data;
            $templateData['class'] = $classData;
            $templateData['pageTitle'] = 'Class metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-class.html.twig', $templateData, $outputFile);
        }
        unset($data, $classesData);
    }

    protected function generateFunctionPage(): void
    {
        $data = $this->dataProviderFactory->getFunctionDataProvider()->getTemplateData();

        $data['pageTitle'] = 'Function metrics';
        $data['currentPage'] = 'functions-list.html';
        $this->renderTemplate('functions-list.html.twig', $data, 'functions-list.html');

        $functionsData = $data['functions'];
        $methodsData = $data['methods'];
        unset($data['functions'], $data['methods']);

        foreach (is_array($functionsData) ? $functionsData : [] as $functionData) {
            $templateData = $data;
            $templateData['isMethod'] = false;
            $templateData['function'] = $functionData;
            $this->createFunctionFile($templateData, 'functions');
        }
        unset($functionsData);

        foreach (is_array($methodsData) ? $methodsData : [] as $functionData) {
            $templateData = $data;
            $templateData['isMethod'] = true;
            $templateData['function'] = $functionData;
            $this->createFunctionFile($templateData, 'methods');
        }
        unset($data, $methodsData);
    }

    protected function generatePackagesPage(): void
    {
        $templateData = $this->dataProviderFactory->getPackageDataProvider();
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

    protected function generateTestsPage(): void
    {
        $data = $this->dataProviderFactory->getTestsDataProvider()->getTemplateData();
        $data['pageTitle'] = 'Tests';
        $data['currentPage'] = 'tests.html';
        $this->renderTemplate('tests.html.twig', $data, 'tests.html');
    }

    protected function generateKnowledgeGraphPage(): void
    {
        $graphProvider = $this->dataProviderFactory->getGraphDataProvider();
        $data = $graphProvider->getTemplateData();
        $data['graphJson'] = json_encode($data['graphData'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
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
        $metricFiles = glob(dirname(__DIR__, 2).'/data/metrics/*.php') ?: [];
        foreach ($metricFiles as $file) {
            $metricsRaw = require $file;
            if (!is_array($metricsRaw)) {
                continue;
            }
            $category = basename($file, '.php');

            foreach ($metricsRaw as $metric) {
                if (!is_array($metric)) {
                    continue;
                }
                if (!isset($metric['name']) || ($metric['type'] ?? '') === 'storage') {
                    continue;
                }
                if (($metric['visibility'] ?? null) === \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere) {
                    continue;
                }

                $valueType = $metric['valueType'] ?? null;
                $better = $metric['better'] ?? null;
                $glossary[$category][] = [
                    'key' => $metric['key'],
                    'name' => $metric['name'],
                    'description' => $metric['description'] ?? '',
                    'valueType' => is_object($valueType) ? ($valueType->name ?? 'Unknown') : 'Unknown',
                    'better' => is_object($better) ? ($better->name ?? 'Irrelevant') : 'Irrelevant',
                ];
            }
        }

        $data['glossary'] = $glossary;
        $data['pageTitle'] = 'Metric Glossary';
        $data['currentPage'] = 'glossary.html';
        $this->renderTemplate('glossary.html.twig', $data, 'glossary.html');
    }
}
