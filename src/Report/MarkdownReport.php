<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MarkdownReport extends HtmlReport
{
    public function __construct(Config $config, DataProviderFactory $dataProviderFactory, false|\DateTimeImmutable $historyDate, FilesystemLoader $twigLoader, Environment $twig, CliOutput $output)
    {
        parent::__construct($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

        $this->reportSubDirName = 'markdown';
        $reportDir = $config->get('reportDir');
        $this->outputDir = (is_string($reportDir) ? $reportDir : '').DIRECTORY_SEPARATOR.'markdown'.DIRECTORY_SEPARATOR;

        $this->templateDir = dirname(__DIR__, 2).'/templates/markdown'.DIRECTORY_SEPARATOR;
        $this->twigLoader->setPaths($this->templateDir);

        // Register markdown parts directory (fallback for templates using @Parts)
        if (is_dir($this->templateDir.'parts')) {
            $this->twigLoader->addPath($this->templateDir.'parts', 'Parts');
        }
    }

    public function generate(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }

        $this->clearReportDir();

        mkdir($this->outputDir.'files');
        mkdir($this->outputDir.'classes');
        mkdir($this->outputDir.'functions');
        mkdir($this->outputDir.'methods');

        $this->generateReportFiles();
    }

    protected function generateKnowledgeGraphPage(): void
    {
        // Knowledge Graph is an interactive D3 visualization — skip for markdown
    }

    /** @param array<string, mixed> $data */
    protected function renderTemplate(string $template, array $data, string $outputFile): void
    {
        $mdTemplate = str_replace('.html.twig', '.md.twig', $template);
        $outputFile = str_replace('.html', '.md', $outputFile);
        $currentPage = $data['currentPage'] ?? '';
        $data['currentPage'] = is_string($currentPage) ? str_replace('.html', '.md', $currentPage) : $currentPage;

        // Prefer .md.twig, fall back to .html.twig (markdown dir uses mixed naming)
        $templateFile = file_exists($this->templateDir.$mdTemplate) ? $mdTemplate : $template;

        $templateWrapper = $this->twig->load($templateFile);
        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir.$outputFile, ob_get_clean());
    }
}
