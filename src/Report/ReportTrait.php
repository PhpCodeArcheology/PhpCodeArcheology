<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

trait ReportTrait
{
    private string $outputDir = '';

    private string $templateDir = '';

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

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function renderTemplate(string $template, array $data, string $outputFile): void
    {
        $templateWrapper = $this->twig->load($template);
        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir . $outputFile, ob_get_clean());
    }
}
