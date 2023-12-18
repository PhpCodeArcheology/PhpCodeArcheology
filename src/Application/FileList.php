<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

class FileList
{
    private array $files = [];

    public function fetch(Config $config): void
    {
        foreach ($config->get('files') as $file) {
            if (is_dir($file)) {
                $dir = new \RecursiveDirectoryIterator($file);
                $iterator = new \RecursiveIteratorIterator($dir);

                foreach ($iterator as $currentFile) {
                    if ($currentFile->getExtension() !== 'php') {
                        continue;
                    }

                    $this->files[] = (string) $currentFile;
                }
            }
            elseif (is_file($file)) {
                $this->files[] = $file;
            }
        }
    }

    /**
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}