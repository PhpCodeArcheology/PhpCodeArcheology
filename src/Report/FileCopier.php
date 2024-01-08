<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

class FileCopier
{
    private array $files = [];

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    public function copyFilesTo(string $destination): void
    {
        foreach ($this->files as $sourceFile) {
            if (! file_exists($sourceFile)) {
                continue;
            }

            if (! is_dir($sourceFile)) {
                $this->copy($sourceFile, $destination);
                continue;
            }

            $this->copy($sourceFile, $destination);
        }
    }

    private function copy(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            return;
        }

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = scandir($source);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sourcePath = $source . '/' . $file;
            $destinationPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->copy($sourcePath, $destinationPath);
            } else {
                copy($sourcePath, $destinationPath);
            }
        }
    }
}
