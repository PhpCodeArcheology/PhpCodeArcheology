<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class PhpunitConfigParser
{
    /**
     * Locate and parse a PHPUnit config file.
     *
     * Searches the composer root for (in order):
     *   1. phpunit.xml
     *   2. phpunit.xml.dist
     *   3. phpunit.dist.xml
     *
     * Returns null when no config file exists or the file cannot be parsed.
     * On parse failure a warning is emitted to STDERR so users aren't surprised
     * their config was silently ignored.
     */
    public function parse(string $composerRoot): ?PhpunitConfigResult
    {
        $configFile = $this->findConfigFile($composerRoot);
        if (null === $configFile) {
            return null;
        }

        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($configFile, options: LIBXML_NONET);
        libxml_use_internal_errors($previousErrors);

        if (false === $xml) {
            fwrite(STDERR, "Warning: Could not parse PHPUnit config: {$configFile}\n");

            return null;
        }

        $configDir = dirname($configFile);
        $testSuites = [];

        $suiteNodes = $xml->xpath('//testsuite') ?: [];
        foreach ($suiteNodes as $suiteNode) {
            $testSuites[] = $this->parseSuiteNode($suiteNode, $configDir);
        }

        [$sourceIncludeDirs, $sourceIncludeFiles, $sourceExcludeDirs, $sourceExcludeFiles]
            = $this->parseSourceNode($xml, $configDir);

        return new PhpunitConfigResult(
            found: true,
            configFilePath: $configFile,
            testSuites: $testSuites,
            sourceIncludeDirectories: $sourceIncludeDirs,
            sourceIncludeFiles: $sourceIncludeFiles,
            sourceExcludeDirectories: $sourceExcludeDirs,
            sourceExcludeFiles: $sourceExcludeFiles,
        );
    }

    /**
     * Parse the top-level <source> element (PHPUnit 10+ coverage scope definition).
     *
     * Returns absolute realpaths for include/exclude directories and files.
     * Missing paths are silently dropped, matching the tolerance of the <testsuite><exclude> parser.
     *
     * @return array{string[], string[], string[], string[]} [includeDirs, includeFiles, excludeDirs, excludeFiles]
     */
    private function parseSourceNode(\SimpleXMLElement $xml, string $configDir): array
    {
        $includeDirs = [];
        $includeFiles = [];
        $excludeDirs = [];
        $excludeFiles = [];

        $sourceNodes = $xml->xpath('/phpunit/source') ?: [];
        foreach ($sourceNodes as $sourceNode) {
            foreach ($sourceNode->include as $includeNode) {
                foreach ($includeNode->directory as $dirNode) {
                    $absPath = $this->resolveEntryPath((string) $dirNode, $configDir, requireDir: true);
                    if (null !== $absPath) {
                        $includeDirs[] = $absPath;
                    }
                }
                foreach ($includeNode->file as $fileNode) {
                    $absPath = $this->resolveEntryPath((string) $fileNode, $configDir, requireFile: true);
                    if (null !== $absPath) {
                        $includeFiles[] = $absPath;
                    }
                }
            }
            foreach ($sourceNode->exclude as $excludeNode) {
                foreach ($excludeNode->directory as $dirNode) {
                    $absPath = $this->resolveEntryPath((string) $dirNode, $configDir, requireDir: true);
                    if (null !== $absPath) {
                        $excludeDirs[] = $absPath;
                    }
                }
                foreach ($excludeNode->file as $fileNode) {
                    $absPath = $this->resolveEntryPath((string) $fileNode, $configDir, requireFile: true);
                    if (null !== $absPath) {
                        $excludeFiles[] = $absPath;
                    }
                }
            }
        }

        return [
            array_values(array_unique($includeDirs)),
            array_values(array_unique($includeFiles)),
            array_values(array_unique($excludeDirs)),
            array_values(array_unique($excludeFiles)),
        ];
    }

    /**
     * Resolve a <source> entry string to an absolute realpath, with an optional
     * file-or-directory sanity check.
     */
    private function resolveEntryPath(string $raw, string $configDir, bool $requireDir = false, bool $requireFile = false): ?string
    {
        $trimmed = trim($raw);
        if ('' === $trimmed) {
            return null;
        }

        $absPath = $this->resolvePath($trimmed, $configDir);
        if (null === $absPath) {
            return null;
        }

        if ($requireDir && !is_dir($absPath)) {
            return null;
        }
        if ($requireFile && !is_file($absPath)) {
            return null;
        }

        return $absPath;
    }

    private function findConfigFile(string $composerRoot): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'] as $filename) {
            $path = $composerRoot.DIRECTORY_SEPARATOR.$filename;
            if (!is_file($path)) {
                continue;
            }

            $real = realpath($path);
            if (false !== $real) {
                return $real;
            }
        }

        return null;
    }

    private function parseSuiteNode(\SimpleXMLElement $suiteNode, string $configDir): PhpunitTestSuite
    {
        $name = (string) ($suiteNode['name'] ?? '');
        $directories = [];
        $files = [];
        $excludedPaths = [];

        foreach ($suiteNode->directory as $dirNode) {
            $relOrAbs = trim((string) $dirNode);
            if ('' === $relOrAbs) {
                continue;
            }

            $absPath = $this->resolvePath($relOrAbs, $configDir);
            if (null === $absPath || !is_dir($absPath)) {
                continue;
            }

            $suffix = trim((string) ($dirNode['suffix'] ?? ''));
            if ('' === $suffix) {
                $suffix = 'Test.php';
            }

            $prefix = trim((string) ($dirNode['prefix'] ?? ''));

            $directories[] = new PhpunitTestSuiteDirectory(
                absolutePath: $absPath,
                suffix: $suffix,
                prefix: $prefix,
            );
        }

        foreach ($suiteNode->file as $fileNode) {
            $relOrAbs = trim((string) $fileNode);
            if ('' === $relOrAbs) {
                continue;
            }

            $absPath = $this->resolvePath($relOrAbs, $configDir);
            if (null === $absPath || !is_file($absPath)) {
                continue;
            }

            $files[] = $absPath;
        }

        foreach ($suiteNode->exclude as $excludeNode) {
            $relOrAbs = trim((string) $excludeNode);
            if ('' === $relOrAbs) {
                continue;
            }

            $absPath = $this->resolvePath($relOrAbs, $configDir);
            if (null === $absPath) {
                // Silently drop missing paths — matches PHPUnit's tolerance.
                continue;
            }

            $excludedPaths[] = $absPath;
        }

        return new PhpunitTestSuite(
            name: $name,
            directories: $directories,
            files: $files,
            excludedPaths: $excludedPaths,
        );
    }

    /**
     * Resolve a directory/file/exclude path from a PHPUnit config to an absolute realpath.
     *
     * Accepts absolute paths as-is. Relative paths are resolved against the config file's
     * directory. Returns null if the path cannot be resolved (does not exist on disk).
     */
    private function resolvePath(string $path, string $configDir): ?string
    {
        $isAbsolute = str_starts_with($path, '/')
            || 1 === preg_match('#^[A-Za-z]:[\\\\/]#', $path);

        $candidate = $isAbsolute ? $path : $configDir.DIRECTORY_SEPARATOR.$path;
        $real = realpath($candidate);

        return false === $real ? null : $real;
    }
}
