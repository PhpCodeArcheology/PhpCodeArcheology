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
        $exclude = $this->config->has('exclude') ? $this->config->get('exclude') : [];
        $exclude = array_map(function($path) {
            return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }, $exclude);

        foreach ($this->config->get('files') as $file) {
            if (is_dir($file)) {
                $file = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $dir = new \RecursiveDirectoryIterator($file);
                $iterator = new \RecursiveIteratorIterator($dir);

                $extensions = $this->config->get('extensions') ?? ['php'];
                $extensionPattern = array_map(function($extension) {
                    $extension = ltrim($extension, '.');
                    return '\.' . $extension;
                }, $extensions);

                $pattern = sprintf(
                    '#^%s%s(%s)$#',
                    preg_quote($file, '#'),
                    ! empty($exclude) ? '((?!' . implode('|', array_map('preg_quote', $exclude)) . ').)+' : '.+',
                    implode('|', $extensionPattern)
                );

                foreach ($iterator as $currentFile) {
                    if (! preg_match($pattern, (string) $currentFile)) {
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
