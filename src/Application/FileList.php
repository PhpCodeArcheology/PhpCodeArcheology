<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

final class FileList
{
    private const DEFAULT_EXCLUDE_DIRS = ['vendor', 'node_modules', '.git'];

    /** @var string[] */
    private array $files = [];

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function fetch(): void
    {
        $excludeRawValue = $this->config->has('exclude') ? $this->config->get('exclude') : [];
        $excludeRaw = is_array($excludeRawValue) ? $excludeRawValue : [];
        $excludeRaw = array_unique(array_merge(self::DEFAULT_EXCLUDE_DIRS, $excludeRaw));
        $exclude = [];
        foreach ($excludeRaw as $path) {
            if (!is_string($path)) {
                continue;
            }
            $resolved = realpath($path);
            if (false !== $resolved) {
                $exclude[] = rtrim($resolved, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            }
        }

        $filesConfig = $this->config->get('files');
        foreach (is_array($filesConfig) ? $filesConfig : [] as $file) {
            if (!is_string($file)) {
                continue;
            }
            $file = realpath($file);

            if (false === $file) {
                continue;
            }

            if (is_dir($file)) {
                $file = rtrim($file, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                try {
                    $dir = new \RecursiveDirectoryIterator($file);
                    $iterator = new \RecursiveIteratorIterator($dir);
                } catch (\UnexpectedValueException) {
                    continue;
                }

                $extensionsConfig = $this->config->get('extensions');
                $extensions = is_array($extensionsConfig) ? $extensionsConfig : ['php'];
                $extensionPattern = array_map(function ($extension): string {
                    $ext = is_string($extension) ? $extension : '';

                    return '\.'.ltrim($ext, '.');
                }, $extensions);

                $pattern = sprintf(
                    '#^%s.+(%s)$#',
                    preg_quote($file, '#'),
                    implode('|', $extensionPattern)
                );

                foreach ($iterator as $currentFile) {
                    if (!$currentFile instanceof \SplFileInfo) {
                        continue;
                    }
                    $filePath = $currentFile->getPathname();

                    if (!preg_match($pattern, $filePath)) {
                        continue;
                    }

                    if ($this->isExcluded($filePath, $exclude)) {
                        continue;
                    }

                    $this->files[] = $filePath;
                }
            } elseif (is_file($file)) {
                if (!$this->isExcluded($file, $exclude)) {
                    $this->files[] = $file;
                }
            }
        }
    }

    /** @param string[] $excludePaths */
    private function isExcluded(string $filePath, array $excludePaths): bool
    {
        foreach ($excludePaths as $excludePath) {
            if (str_starts_with($filePath, (string) $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /** @param string[] $paths */
    public function removeFiles(array $paths): void
    {
        $remove = array_flip($paths);
        $this->files = array_values(array_filter(
            $this->files,
            static fn (string $file): bool => !isset($remove[$file]),
        ));
    }

    /** @return string[] */
    public function getFiles(): array
    {
        return $this->files;
    }
}
