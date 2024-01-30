<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
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
        protected readonly FilesystemLoader $twigLoader,
        protected readonly Environment      $twig,
        private readonly CliOutput          $output)
    {
        $this->outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;

        $this->templateDir = realpath(__DIR__ . '/../../templates/html') . DIRECTORY_SEPARATOR;
        $this->assetDir = $this->templateDir . 'assets';

        if (! is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->twigLoader->setPaths($this->templateDir);
        $this->twig->setCache(false);
    }

    public function generate(): void
    {
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
            [$this, 'generateFilesPages'],
            [$this, 'generateClassPage'],
            [$this, 'generateClassesPages'],
            [$this, 'generateClassCouplingPage'],
            [$this, 'generateClassChartPage'],
            [$this, 'generatePackagesPage'],
            [$this, 'generateFunctionPage'],
            [$this, 'generateFunctionsPages'],
            [$this, 'generateProblemsPage'],
        ];

        $count = 0;
        $countSum = count($createMethods);
        foreach ($createMethods as $method) {
            $this->output->cls();
            $this->output->outWithMemory(
                "Creating report part \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m..."
            );

            ++ $count;

            call_user_func($method);
        }

        $this->output->outNl("\033[32m"."Report ready.\033[0m");
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
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'index.html';

        $this->renderTemplate('index.html.twig', $data, 'index.html');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function generateFilePage(): void
    {
        $templateData = $this->dataProviderFactory->getFilesDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'files.html';
        $this->renderTemplate('files.html.twig', $data, 'files.html');
    }

    protected function generateFilesPages(): void
    {
        $templateData = $this->dataProviderFactory->getFilesDataProvider();
        $data = $templateData->getTemplateData();

        foreach ($data['files'] as $fileKey => $fileData) {
            $outputFile = 'files/' . $fileKey . '.html';

            $templateData = $data;
            unset($templateData['files']);

            $templateData['file'] = $fileData;

            $templateData['pageTitle'] = 'File metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-file.html.twig', $templateData, $outputFile);
        }

    }

    protected function generateClassesPages(): void
    {
        $templateData = $this->dataProviderFactory->getClassDataProvider();
        $data = $templateData->getTemplateData();

        foreach ($data['classes'] as $classKey => $classData) {
            $outputFile = 'classes/' . $classKey . '.html';

            $templateData = $data;
            unset($templateData['classes']);

            $templateData['class'] = $classData;

            $templateData['pageTitle'] = 'Class metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-class.html.twig', $templateData, $outputFile);
        }
    }

    protected function generateClassPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');
    }

    protected function generateFunctionPage(): void
    {
        $templateData = $this->dataProviderFactory->getFunctionDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Function metrics';
        $data['currentPage'] = 'functions-list.html';
        $this->renderTemplate('functions-list.html.twig', $data, 'functions-list.html');
    }

    protected function generateFunctionsPages(): void
    {
        $templateData = $this->dataProviderFactory->getFunctionDataProvider();
        $data = $templateData->getTemplateData();

        foreach ($data['functions'] as $functionData) {
            $templateData = $data;
            unset($templateData['functions']);
            unset($templateData['methods']);

            $templateData['isMethod'] = false;
            $templateData['function'] = $functionData;

            $this->createFunctionFile($templateData, 'functions');
        }

        foreach ($data['methods'] as $functionData) {
            $templateData = $data;
            unset($templateData['functions']);
            unset($templateData['methods']);

            $templateData['isMethod'] = true;
            $templateData['function'] = $functionData;

            $this->createFunctionFile($templateData, 'methods');
        }
    }

    protected function generatePackagesPage(): void
    {
        $templateData = $this->dataProviderFactory->getPackagDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'packages-list.html';
        $data['usesCharts'] = true;
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
}
