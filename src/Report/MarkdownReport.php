<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\ReportDataProviderInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MarkdownReport extends HtmlReport
{
    public function __construct(Config $config, DataProviderFactory $dataProviderFactory, false|\DateTimeImmutable $historyDate, FilesystemLoader $twigLoader, Environment $twig, CliOutput $output)
    {
        parent::__construct($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

        $this->templateDir = realpath(__DIR__ . '/../../templates/markdown') . DIRECTORY_SEPARATOR;
        $this->twigLoader->setPaths($this->templateDir);

        // Register markdown parts directory (fallback for templates using @Parts)
        if (is_dir($this->templateDir . 'parts')) {
            $this->twigLoader->addPath($this->templateDir . 'parts', 'Parts');
        }
    }

    public function generate(): void
    {
        $this->clearReportDir();

        mkdir($this->outputDir . 'files');
        mkdir($this->outputDir . 'classes');
        mkdir($this->outputDir . 'functions');
        mkdir($this->outputDir . 'methods');

        $this->generateReportFiles();
    }

    protected function renderTemplate(string $template, array $data, string $outputFile): void
    {
        $mdTemplate = str_replace('.html.twig', '.md.twig', $template);
        $outputFile = str_replace('.html', '.md', $outputFile);
        $data['currentPage'] = str_replace('.html', '.md', $data['currentPage']);

        // Prefer .md.twig, fall back to .html.twig (markdown dir uses mixed naming)
        $templateFile = file_exists($this->templateDir . $mdTemplate) ? $mdTemplate : $template;

        $templateWrapper = $this->twig->load($templateFile);
        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir . $outputFile, ob_get_clean());
    }
}
