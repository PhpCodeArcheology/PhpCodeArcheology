<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\Config;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class MarkdownReport implements ReportInterface
{
    private string $outputDir = '';

    private string $templateDir = '';

    public function __construct(private Config $config, private ReportData $reportData)
    {
        $this->outputDir = rtrim($config->get('runningDir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR;
        $this->templateDir = realpath(__DIR__ . '/../../templates/markdown') . DIRECTORY_SEPARATOR;

        if (! is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }
    }

    public function generate(): void
    {
        $this->renderTemplate('index.php', 'report.md');
        $this->renderTemplate('class-dependencies.php', 'class-dependencies.md');
    }

    private function renderTemplate(string $templateFile, string $outputFile): void
    {
        ob_start();
        require $this->templateDir . $templateFile;
        $content = ob_get_clean();

        file_put_contents($this->outputDir . $outputFile, $content);
    }

    private function renderTable(array $head = [], array $data = []): string
    {
        if (empty($data)) {
            return '';
        }

        $header = implode(' | ', $head) . "\n";
        $header .= implode(' | ', array_fill(0, count($head), '-')) . "\n";

        $table = array_map(fn($items) => implode(' | ', $items), $data);

        return $header . implode("\n", $table);
    }
}