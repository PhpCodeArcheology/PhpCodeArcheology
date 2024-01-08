<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\Config;
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
        private readonly Config           $config,
        private readonly ReportData       $reportData,
        private readonly FilesystemLoader $twigLoader,
        private readonly Environment      $twig)
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

        $this->generateIndexPage();
        $this->generateFilesPage();
        $this->generateFilesPages();
        $this->generateClassPage();
        $this->generateClassPages();
        $this->generateClassChartPage();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function generateIndexPage(): void
    {
        $templateData = $this->reportData->getProjectData();
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
    private function generateFilesPage(): void
    {
        $templateData = $this->reportData->getFiles();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Project metrics';
        $data['currentPage'] = 'files.html';
        $this->renderTemplate('files.html.twig', $data, 'files.html');
    }

    private function generateClassChartPage(): void
    {
        $templateData = $this->reportData->getClassAIChartData();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class chart';
        $data['currentPage'] = 'classes-chart.html';
        $data['usesCharts'] = true;
        $this->renderTemplate('classes-chart.html.twig', $data, 'classes-chart.html');
    }

    private function generateFilesPages(): void
    {
        $templateData = $this->reportData->getFiles();
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

    private function generateClassPages()
    {
        $templateData = $this->reportData->getClasses();
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
        $templateData = $this->reportData->getClasses();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'Class metrics';
        $data['currentPage'] = 'classes-list.html';
        $this->renderTemplate('classes.html.twig', $data, 'classes-list.html');
    }
}
