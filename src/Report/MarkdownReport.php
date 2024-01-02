<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\Config;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;

class MarkdownReport implements ReportInterface
{
    use ReportTrait;

    public function __construct(
        private Config $config,
        private ReportData $reportData,
        private FilesystemLoader $twigLoader,
        private Environment $twig
    )
    {
        $this->outputDir = rtrim($config->get('runningDir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR;
        $this->templateDir = realpath(__DIR__ . '/../../templates/markdown') . DIRECTORY_SEPARATOR;

        if (! is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->twigLoader->setPaths($this->templateDir);
        $this->twig->setCache(false);
    }

    public function generate(): void
    {
        $this->clearReportDir();

        mkdir($this->outputDir . '/files');

        $templateData = $this->reportData->getProjectData();
        $this->renderTemplate('index.md.twig', $templateData->getTemplateData(), 'index.md');

        $templateData = $this->reportData->getFiles();
        $this->renderTemplate('files.md.twig', $templateData->getTemplateData(), 'files.md');

        foreach ($templateData->getFiles() as $fileData) {
            $fileData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->renderTemplate('file.md.twig', $fileData, 'files/' . $fileData['id'] . '.md');
        }
    }
}
