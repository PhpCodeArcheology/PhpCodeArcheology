<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class TestDirectoryScanner
{
    public function __construct(
        private readonly ?FrameworkDetectionResult $frameworkDetection = null,
    ) {
    }

    public function scan(string $projectRoot): TestScanResult
    {
        $projectRoot = rtrim($projectRoot, '/\\');
        $composerRoot = $this->resolveComposerRoot($projectRoot);

        $phpunitConfig = (new PhpunitConfigParser())->parse($composerRoot);

        if (null !== $phpunitConfig && $phpunitConfig->hasTestSuites()) {
            [$testFiles, $testDirectories] = $this->collectFromPhpunitConfig($phpunitConfig);
        } else {
            $testDirectories = $this->findTestDirectoriesLegacy($projectRoot, $composerRoot);
            $testFiles = [];
            foreach ($testDirectories as $testDir) {
                foreach ($this->findTestFilesInDirectoryLegacy($testDir) as $file) {
                    $testFiles[] = $file;
                }
            }
        }

        $classBasedTestFiles = [];
        $functionBasedTestFiles = [];
        $testFileToType = [];

        foreach ($testFiles as $file) {
            $testFileToType[$file] = $this->determineTestType($file);

            if ($this->hasClassDeclaration($file)) {
                $classBasedTestFiles[] = $file;
            } else {
                $functionBasedTestFiles[] = $file;
            }
        }

        return new TestScanResult(
            testDirectories: $testDirectories,
            classBasedTestFiles: $classBasedTestFiles,
            functionBasedTestFiles: $functionBasedTestFiles,
            testFileToType: $testFileToType,
            phpunitConfig: $phpunitConfig,
        );
    }

    private function resolveComposerRoot(string $projectRoot): string
    {
        if (null === $this->frameworkDetection) {
            return $projectRoot;
        }

        $composerJsonPath = $this->frameworkDetection->composerJsonPath;
        if ('' !== $composerJsonPath) {
            return dirname($composerJsonPath);
        }

        return $projectRoot;
    }

    /**
     * Collect test files and directories from a parsed PHPUnit config.
     *
     * Honors per-directory suffix/prefix, explicit <file> entries, and <exclude> entries.
     *
     * @return array{0: string[], 1: string[]} [testFiles, testDirectories]
     */
    private function collectFromPhpunitConfig(PhpunitConfigResult $cfg): array
    {
        $files = [];
        $dirs = [];

        foreach ($cfg->getAllDirectories() as $directory) {
            $dirs[] = $directory->absolutePath;

            foreach ($this->iterateFilesRecursive($directory->absolutePath) as $filePath) {
                if ($cfg->isExcluded($filePath)) {
                    continue;
                }

                $name = basename($filePath);
                if (!str_ends_with($name, $directory->suffix)) {
                    continue;
                }
                if ('' !== $directory->prefix && !str_starts_with($name, $directory->prefix)) {
                    continue;
                }

                $files[] = $filePath;
            }
        }

        foreach ($cfg->getAllExplicitFiles() as $explicitFile) {
            if ($cfg->isExcluded($explicitFile)) {
                continue;
            }
            $files[] = $explicitFile;
        }

        return [
            array_values(array_unique($files)),
            array_values(array_unique($dirs)),
        ];
    }

    /**
     * Legacy discovery path used when no phpunit.xml is present (or has no testsuites).
     *
     * @return string[]
     */
    private function findTestDirectoriesLegacy(string $projectRoot, string $composerRoot): array
    {
        $dirs = [];

        // Primary legacy: use PSR-4 autoload-dev directories from composer.json
        if ($this->frameworkDetection instanceof FrameworkDetectionResult && [] !== $this->frameworkDetection->psr4AutoloadDev) {
            foreach ($this->frameworkDetection->psr4AutoloadDev as $relPath) {
                $absPath = $composerRoot.DIRECTORY_SEPARATOR.$relPath;
                $real = realpath($absPath);
                if (false !== $real && is_dir($real)) {
                    $dirs[] = $real;
                }
            }
        }

        // Secondary legacy: scan for common test directory names
        if ([] === $dirs) {
            foreach (['tests', 'test', 'spec'] as $dirName) {
                foreach (array_unique([$projectRoot, $composerRoot]) as $base) {
                    $candidate = $base.DIRECTORY_SEPARATOR.$dirName;
                    $real = realpath($candidate);
                    if (false !== $real && is_dir($real)) {
                        $dirs[] = $real;
                    }
                }
            }
        }

        return array_values(array_unique($dirs));
    }

    /**
     * Legacy file matcher: hard-coded Test.php / Spec.php / Cest.php suffix set.
     * Used by the fallback path (no phpunit.xml). Codeception and Pest-without-phpunit.xml
     * projects rely on this.
     *
     * @return string[]
     */
    private function findTestFilesInDirectoryLegacy(string $dir): array
    {
        $files = [];

        foreach ($this->iterateFilesRecursive($dir) as $filePath) {
            $filename = basename($filePath);
            if (
                str_ends_with($filename, 'Test.php')
                || str_ends_with($filename, 'Spec.php')
                || str_ends_with($filename, 'Cest.php')
            ) {
                $files[] = $filePath;
            }
        }

        return $files;
    }

    /**
     * @return iterable<string>
     */
    private function iterateFilesRecursive(string $dir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            yield $file->getPathname();
        }
    }

    private function hasClassDeclaration(string $filePath): bool
    {
        $content = @file_get_contents($filePath);
        if (false === $content) {
            return false;
        }

        return (bool) preg_match('/\bclass\s+\w+/', $content);
    }

    private function determineTestType(string $filePath): string
    {
        $normalizedPath = str_replace('\\', '/', $filePath);

        if (str_contains($normalizedPath, '/Unit/')) {
            return 'unit';
        }

        if (str_contains($normalizedPath, '/Feature/') || str_contains($normalizedPath, '/Integration/')) {
            return 'integration';
        }

        return 'unknown';
    }
}
