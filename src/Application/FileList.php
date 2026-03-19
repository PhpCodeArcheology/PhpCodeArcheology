<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

final class FileList
{
    private array $files = [];

    public function __construct(
        private readonly Config $config
    )
    {}

    public function fetch(): void
    {
        $excludeRaw = $this->config->has('exclude') ? $this->config->get('exclude') : [];
        $exclude = [];
        foreach ($excludeRaw as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                $exclude[] = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }

        foreach ($this->config->get('files') as $file) {
            $file = realpath($file);

            if ($file === false) {
                continue;
            }

            if (is_dir($file)) {
                $file = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                try {
                    $dir = new \RecursiveDirectoryIterator($file);
                    $iterator = new \RecursiveIteratorIterator($dir);
                } catch (\UnexpectedValueException $e) {
                    continue;
                }

                $extensions = $this->config->get('extensions') ?? ['php'];
                $extensionPattern = array_map(function($extension) {
                    $extension = ltrim($extension, '.');
                    return '\.' . $extension;
                }, $extensions);

                $pattern = sprintf(
                    '#^%s.+(%s)$#',
                    preg_quote($file, '#'),
                    implode('|', $extensionPattern)
                );

                foreach ($iterator as $currentFile) {
                    $filePath = (string) $currentFile;

                    if (!preg_match($pattern, $filePath)) {
                        continue;
                    }

                    if ($this->isExcluded($filePath, $exclude)) {
                        continue;
                    }

                    $this->files[] = $filePath;
                }
            }
            elseif (is_file($file)) {
                if (!$this->isExcluded($file, $exclude)) {
                    $this->files[] = $file;
                }
            }
        }
    }

    private function isExcluded(string $filePath, array $excludePaths): bool
    {
        foreach ($excludePaths as $excludePath) {
            if (str_starts_with($filePath, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
