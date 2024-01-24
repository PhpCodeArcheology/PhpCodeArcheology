<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\Application;
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
        private readonly Config              $config,
        private readonly DataProviderFactory $dataProviderFactory,
        private readonly FilesystemLoader    $twigLoader,
        private readonly Environment         $twig,
        private readonly CliOutput           $output)
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

        $fc = new FileCopier();
        $fc->setFiles([
            $this->templateDir . 'assets',
        ]);
        $fc->copyFilesTo(rtrim($this->outputDir . '/assets', DIRECTORY_SEPARATOR));

        $this->generateReportFiles();
    }

    private function generateReportFiles(): void
    {
        $createMethods = [
            'indexPage',
            'filePage',
            'filesPages',
            'classPage',
            'classesPages',
            'classCouplingPage',
            'classChartPage',
            'packagesPage',
            'functionPage',
            'functionsPages',
        ];

        $count = 0;
        $countSum = count($createMethods);
        foreach ($createMethods as $method) {
            $this->output->cls();
            $this->output->out(
                "Creating report part \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            $methodName = sprintf('generate%s', ucfirst($method));
            $this->$methodName();
        }

        $this->output->outNl("\033[32m"."Report ready.\033[0m");
        $this->output->outNl();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function generateIndexPage(): void
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
    private function generateFilePage(): void
    {
        $templateData = $this->dataProviderFactory->getFilesDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'files.html';
        $this->renderTemplate('files.html.twig', $data, 'files.html');
    }

    private function generateFilesPages(): void
    {
        $templateData = $this->dataProviderFactory->getFilesDataProvider();
        $data = $templateData->getTemplateData();

        foreach ($data['files'] as $fileKey => $fileData) {
            $outputFile = 'files/' . $fileData['id'] . '.html';

            $templateData = [];
            $templateData['file'] = $fileData;
            $templateData['version'] = Application::VERSION;

            $templateData['pageTitle'] = 'File metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-file.html.twig', $templateData, $outputFile);
        }

    }

    private function generateClassesPages()
    {
        $templateData = $this->dataProviderFactory->getClassDataProvider();
        $data = $templateData->getTemplateData();

        foreach ($data['classes'] as $classKey => $classData) {
            $outputFile = 'classes/' . $classData['id'] . '.html';

            $templateData = [];
            $templateData['class'] = $classData;
            $templateData['version'] = Application::VERSION;

            $templateData['pageTitle'] = 'Class metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-class.html.twig', $templateData, $outputFile);
        }
    }

    private function generateClassPage()
    {
        $templateData = $this->dataProviderFactory->getClassDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');
    }

    private function generateFunctionPage()
    {
        $templateData = $this->dataProviderFactory->getFunctionDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Function metrics';
        $data['currentPage'] = 'functions-list.html';
        $this->renderTemplate('functions-list.html.twig', $data, 'functions-list.html');
    }

    private function generateFunctionsPages()
    {
        $templateData = $this->dataProviderFactory->getFunctionDataProvider();
        $data = $templateData->getTemplateData();

        $functions = array_merge($data['functions'], $data['methods']);

        foreach ($functions as $functionData) {
            $outputFile = 'functions/' . $functionData['id'] . '.html';

            $templateData = [];
            $templateData['function'] = $functionData;
            $templateData['version'] = Application::VERSION;

            $templateData['pageTitle'] = 'Function metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-function.html.twig', $templateData, $outputFile);
        }
    }

    private function generatePackagesPage(): void
    {
        $templateData = $this->dataProviderFactory->getPackagDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'packages-list.html';
        $data['usesCharts'] = true;
        $this->renderTemplate('packages-list.html.twig', $data, 'packages-list.html');
    }

    private function generateClassCouplingPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassCouplingDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class coupling';
        $data['currentPage'] = 'class-coupling.html';
        $this->renderTemplate('class-coupling.html.twig', $data, 'class-coupling.html');
    }

    private function generateClassChartPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassesChartDataProvider();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Classes chart';
        $data['currentPage'] = 'classes-chart.html';
        $this->renderTemplate('classes-chart.html.twig', $data, 'classes-chart.html');
    }
}
