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

        $fc = new FileCopier();
        $fc->setFiles([
            $this->templateDir . 'assets',
        ]);
        $fc->copyFilesTo(rtrim($this->outputDir . '/assets', DIRECTORY_SEPARATOR));

        $this->generateIndexPage();
        $this->generateFilesPage();
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
}
