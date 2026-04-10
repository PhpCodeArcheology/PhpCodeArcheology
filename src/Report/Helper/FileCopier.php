<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Helper;

class FileCopier
{
    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $excludeDirs = [];

    /** @param list<string> $files */
    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    /** @param list<string> $excludeDirs */
    public function setExcludeDirs(array $excludeDirs): void
    {
        $this->excludeDirs = $excludeDirs;
    }

    public function copyFilesTo(string $destination): void
    {
        foreach ($this->files as $sourceFile) {
            if (!file_exists($sourceFile)) {
                continue;
            }

            if (!is_dir($sourceFile)) {
                $this->copy($sourceFile, $destination);
                continue;
            }

            $this->copy($sourceFile, $destination);
        }
    }

    private function copy(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = scandir($source);

        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            $sourcePath = $source.'/'.$file;
            $destinationPath = $destination.'/'.$file;

            if (is_dir($sourcePath)) {
                if (in_array($file, $this->excludeDirs, true)) {
                    continue;
                }
                $this->copy($sourcePath, $destinationPath);
            } else {
                copy($sourcePath, $destinationPath);
            }
        }
    }
}
