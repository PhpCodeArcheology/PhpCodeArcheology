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
    private string $outputDir = '';

    private string $cacheDir = '';

    private string $templateDir = '';

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

        $createDate = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $metrics = [
            'overallFiles' => 'Files',
            'overallFileErrors' => 'File errors',
            'overallFunctions' => 'Functions',
            'overallClasses' => 'Classes',
            'overallAbstractClasses' => 'Abstract classes',
            'overallInterfaces' => 'Interfaces',
            'overallMethods' => 'Methods',
            'overallPrivateMethods' => 'Private methods',
            'overallPublicMethods' => 'Public methods',
            'overallStaticMethods' => 'Static methods',
            'overallLoc' => 'Lines of code',
            'overallCloc' => 'Comment lines',
            'overallLloc' => 'Logical lines of code',
            'overallMaxCC' => 'Max. cyclomatic complexity',
            'overallMostComplexFile' => 'Most complex file',
            'overallMostComplexClass' => 'Most complex class',
            'overallMostComplexMethod' => 'Most complex method',
            'overallMostComplexFunction' => 'Most complex function',
            'overallAvgCC' => 'Average complexity',
            'overallAvgCCFile' => 'Average file complexity',
            'overallAvgCCClass' => 'Average class complexity',
            'overallAvgCCMethod' => 'Average method complexity',
            'overallAvgCCFunction' => 'Average function complexity',
        ];

        $overallData = $this->reportData->getOverallData();

        $data = [];
        foreach ($metrics as $key => $label) {
            $value = is_numeric($overallData[$key]) ? number_format($overallData[$key]) : $overallData[$key];

            $data[] = ['name' => $label, 'value' => $value];
        }

        $this->renderTemplate('index.md.twig', [
            'datetime' => $createDate,
            'elements' => $data,
        ], 'index.md');

        $files = $this->reportData->getFiles();
        foreach ($files as $fileName => &$fileData) {
            $mdFile = 'files/' . $fileData['id'] . '.md';
            $this->renderTemplate('file.md.twig', [
                'fileName' => $fileName,
            ], $mdFile);

            $fileData['name'] = $fileName;
        }

        $this->renderTemplate('files.md.twig', [
            'datetime' => $createDate,
            'files' => $files,
        ], 'files.md');
    }

    private function renderTemplate(string $template, array $data, string $outputFile): void
    {
        $templateWrapper = $this->twig->load($template);
        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir . $outputFile, ob_get_clean());
    }

    private function clearReportDir(): void
    {
        $this->deleteDirContents($this->outputDir);
    }

    private function deleteDirContents(string $dir): void
    {
        if (!str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDirContents($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }
}
