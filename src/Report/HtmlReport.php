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

    public function __construct(
        private readonly Config           $config,
        private readonly ReportData       $reportData,
        private readonly FilesystemLoader $twigLoader,
        private readonly Environment      $twig)
    {
        $this->outputDir = rtrim($config->get('runningDir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR;
        $this->templateDir = realpath(__DIR__ . '/../../templates/html') . DIRECTORY_SEPARATOR;

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

        $templateData = $this->reportData->getProjectData();
        $data = $templateData->getTemplateData();
        $data['pageTitle'] = 'PhpLegacyArcheology';

        $this->renderTemplate('index.html.twig', $data, 'index.html');
    }
}
