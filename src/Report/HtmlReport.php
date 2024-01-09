<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\Data\DataProviderFactory;
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
        $this->outputDir = rtrim($config->get('runningDir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR;
        $this->templateDir = realpath(__DIR__ . '/../../templates/html') . DIRECTORY_SEPARATOR;
        $this->assetDir = $this->templateDir . 'assets';

        if (! is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->twigLoader->setPaths($this->templateDir);
        $this->twig->setCache(false);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function generate(): void
    {
        $this->clearReportDir();

        mkdir(directory: $this->outputDir . 'assets', recursive: true);
        mkdir($this->outputDir . 'files');
        mkdir($this->outputDir . 'classes');

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
            'classesChartPage',
            'packagesPage',
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
        $templateData = $this->dataProviderFactory->getProjectData();
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
        $templateData = $this->dataProviderFactory->getFiles();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'files.html';
        $this->renderTemplate('files.html.twig', $data, 'files.html');
    }

    private function generateClassesChartPage(): void
    {
        $templateData = $this->dataProviderFactory->getClassAIChartData();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class chart';
        $data['currentPage'] = 'classes-chart.html';
        $data['usesCharts'] = true;
        $this->renderTemplate('classes-chart.html.twig', $data, 'classes-chart.html');
    }

    private function generateFilesPages(): void
    {
        $templateData = $this->dataProviderFactory->getFiles();
        $data = $templateData->getTemplateData();

        foreach ($data['files'] as $fileKey => $fileData) {
            $outputFile = 'files/' . $fileData['id'] . '.html';

            $templateData = $data;
            unset($templateData['files']);
            $templateData['file'] = $fileData;

            $templateData['pageTitle'] = 'File metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-file.html.twig', $templateData, $outputFile);
        }

    }

    private function generateClassesPages()
    {
        $templateData = $this->dataProviderFactory->getClasses();
        $data = $templateData->getTemplateData();

        foreach ($data['classes'] as $classKey => $classData) {
            $outputFile = 'classes/' . $classData['id'] . '.html';

            $templateData = $data;
            unset($templateData['classes']);
            $templateData['class'] = $classData;

            $templateData['pageTitle'] = 'Class metrics';
            $templateData['currentPage'] = $outputFile;
            $templateData['isSubdir'] = true;

            $this->renderTemplate('single-class.html.twig', $templateData, $outputFile);
        }
    }

    private function generateClassPage()
    {
        $templateData = $this->dataProviderFactory->getClasses();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');
    }

    private function generatePackagesPage(): void
    {
        $templateData = $this->dataProviderFactory->getPackages();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'packages-list.html';
        $this->renderTemplate('packages-list.html.twig', $data, 'packages-list.html');
    }
}
