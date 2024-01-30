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
    public function __construct(Config $config, DataProviderFactory $dataProviderFactory, FilesystemLoader $twigLoader, Environment $twig, CliOutput $output)
    {
        parent::__construct($config, $dataProviderFactory, $twigLoader, $twig, $output);

        $this->templateDir = realpath(__DIR__ . '/../../templates/markdown') . DIRECTORY_SEPARATOR;
        $this->twigLoader->setPaths($this->templateDir);
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
        $template = str_replace('.html.twig', '.md.twig', $template);
        $outputFile = str_replace('.html', '.md', $outputFile);
        $data['currentPage'] = str_replace('.html', '.md', $data['currentPage']);

        $templateWrapper = $this->twig->load($template);
        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir . $outputFile, ob_get_clean());
    }
}
