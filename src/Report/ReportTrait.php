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
        $this->deleteDirContents($this->outputDir);
    }

    protected function deleteDirContents(string $dir): void
    {
        if (!str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (str_ends_with($file, 'history.json')) {
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
