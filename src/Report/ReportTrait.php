<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

trait ReportTrait
{
    protected string $outputDir = '';

    protected string $templateDir = '';

    protected function clearReportDir(): void
    {
        if ($this->outputDir === '' || $this->outputDir === '/' || $this->outputDir === DIRECTORY_SEPARATOR) {
            throw new \RuntimeException('Refusing to delete root directory. reportDir is not set correctly.');
        }

        // Safety: reportDir must be at least 3 levels deep (e.g. /a/b/c)
        $resolved = realpath($this->outputDir);
        if ($resolved !== false && substr_count(rtrim($resolved, '/'), '/') < 3) {
            throw new \RuntimeException(
                "Refusing to delete directory '{$resolved}': path is too close to filesystem root."
            );
        }

        $this->deleteDirContents($this->outputDir);
    }

    protected function deleteDirContents(string $dir): void
    {
        if (!str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        if ($dir === '/' || $dir === '//') {
            throw new \RuntimeException('Refusing to delete root directory.');
        }

        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (str_ends_with($file, 'history.json') || str_ends_with($file, 'history.jsonl')) {
                continue;
            }

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
    protected function renderTemplate(string $template, array $data, string $outputFile): void
    {
        $templateWrapper = $this->twig->load($template);

        $data['hasHistory'] = $this->historyDate !== false;
        $data['historyDate'] = $this->historyDate;

        ob_start();
        echo $templateWrapper->render($data);
        file_put_contents($this->outputDir . $outputFile, ob_get_clean());
    }
}
