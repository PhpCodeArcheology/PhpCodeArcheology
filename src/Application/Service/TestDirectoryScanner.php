<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class TestDirectoryScanner
{
    public function __construct(
        private readonly ?FrameworkDetectionResult $frameworkDetection = null
    ) {
    }

    public function scan(string $projectRoot): TestScanResult
    {
        $projectRoot = rtrim($projectRoot, '/\\');
        $composerRoot = $this->resolveComposerRoot($projectRoot);

        $testDirectories = $this->findTestDirectories($projectRoot, $composerRoot);

        $classBasedTestFiles = [];
        $functionBasedTestFiles = [];
        $testFileToType = [];

        foreach ($testDirectories as $testDir) {
            foreach ($this->findTestFilesInDirectory($testDir) as $file) {
                $testFileToType[$file] = $this->determineTestType($file);

                if ($this->hasClassDeclaration($file)) {
                    $classBasedTestFiles[] = $file;
                } else {
                    $functionBasedTestFiles[] = $file;
                }
            }
        }

        return new TestScanResult(
            testDirectories: $testDirectories,
            classBasedTestFiles: $classBasedTestFiles,
            functionBasedTestFiles: $functionBasedTestFiles,
            testFileToType: $testFileToType,
        );
    }

    private function resolveComposerRoot(string $projectRoot): string
    {
        if ($this->frameworkDetection?->composerJsonPath !== '') {
            return dirname((string) $this->frameworkDetection?->composerJsonPath);
        }
        return $projectRoot;
    }

    private function findTestDirectories(string $projectRoot, string $composerRoot): array
    {
        $dirs = [];

        // Primary: use PSR-4 autoload-dev directories from composer.json
        if ($this->frameworkDetection !== null && !empty($this->frameworkDetection->psr4AutoloadDev)) {
            foreach ($this->frameworkDetection->psr4AutoloadDev as $relPath) {
                $absPath = $composerRoot . DIRECTORY_SEPARATOR . $relPath;
                $real = realpath($absPath);
                if ($real !== false && is_dir($real)) {
                    $dirs[] = $real;
                }
            }
        }

        // Fallback: scan for common test directory names
        if (empty($dirs)) {
            foreach (['tests', 'test', 'spec'] as $dirName) {
                foreach (array_unique([$projectRoot, $composerRoot]) as $base) {
                    $candidate = $base . DIRECTORY_SEPARATOR . $dirName;
                    $real = realpath($candidate);
                    if ($real !== false && is_dir($real)) {
                        $dirs[] = $real;
                    }
                }
            }
        }

        return array_values(array_unique($dirs));
    }

    private function findTestFilesInDirectory(string $dir): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filename = $file->getFilename();
            if (
                str_ends_with($filename, 'Test.php') ||
                str_ends_with($filename, 'Spec.php') ||
                str_ends_with($filename, 'Cest.php')
            ) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function hasClassDeclaration(string $filePath): bool
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
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
