<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class PhpunitConfigResult
{
    /**
     * @param PhpunitTestSuite[] $testSuites
     * @param string[]           $sourceIncludeDirectories Absolute realpaths of <source><include><directory>
     * @param string[]           $sourceIncludeFiles       Absolute realpaths of <source><include><file>
     * @param string[]           $sourceExcludeDirectories Absolute realpaths of <source><exclude><directory>
     * @param string[]           $sourceExcludeFiles       Absolute realpaths of <source><exclude><file>
     */
    public function __construct(
        public bool $found = false,
        public string $configFilePath = '',
        public array $testSuites = [],
        public array $sourceIncludeDirectories = [],
        public array $sourceIncludeFiles = [],
        public array $sourceExcludeDirectories = [],
        public array $sourceExcludeFiles = [],
    ) {
    }

    /**
     * True when the config defined at least one testsuite with directories or files.
     */
    public function hasTestSuites(): bool
    {
        foreach ($this->testSuites as $suite) {
            if ([] !== $suite->directories || [] !== $suite->files) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flattened list of all directories across all testsuites.
     *
     * @return PhpunitTestSuiteDirectory[]
     */
    public function getAllDirectories(): array
    {
        $all = [];
        foreach ($this->testSuites as $suite) {
            foreach ($suite->directories as $dir) {
                $all[] = $dir;
            }
        }

        return $all;
    }

    /**
     * Flattened, deduped list of all explicit <file> entries across all testsuites.
     *
     * @return string[]
     */
    public function getAllExplicitFiles(): array
    {
        $all = [];
        foreach ($this->testSuites as $suite) {
            foreach ($suite->files as $file) {
                $all[] = $file;
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * True if the given absolute path matches any <exclude> entry across all testsuites.
     *
     * Matching semantics:
     *  - Exact string match (file-level exclude)
     *  - Directory prefix match with trailing '/' (prevents tests/UnitLegacy from matching tests/Unit)
     *
     * All comparisons operate on forward-slash-normalized realpath strings.
     */
    public function isExcluded(string $absPath): bool
    {
        $normalized = $this->normalize($absPath);

        foreach ($this->testSuites as $suite) {
            foreach ($suite->excludedPaths as $excluded) {
                $excludedNorm = $this->normalize($excluded);

                if ($normalized === $excludedNorm) {
                    return true;
                }

                if (is_dir($excluded) && str_starts_with($normalized, $excludedNorm.'/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * True if the config defined a <source> section with include/exclude paths.
     *
     * When false, isInSourceScope() returns true for every path (no scope configured
     * ⇒ everything is in scope, matching the pre-2.9.1 behavior).
     */
    public function hasSourceScope(): bool
    {
        return [] !== $this->sourceIncludeDirectories
            || [] !== $this->sourceIncludeFiles
            || [] !== $this->sourceExcludeDirectories
            || [] !== $this->sourceExcludeFiles;
    }

    /**
     * True if the given absolute path is part of the coverage scope defined by <source>.
     *
     * Rules (matching PHPUnit's behavior):
     *  - No <source> configured → every path is in scope.
     *  - <exclude> always wins over <include> for the same path.
     *  - Path is in scope iff it sits under an include directory (or equals an include file)
     *    AND is not under any exclude directory and not equal to any exclude file.
     *  - If <include> is empty but <exclude> is set, every path not excluded is in scope.
     *
     * All comparisons operate on forward-slash-normalized paths with trailing '/' guards
     * on directory prefixes to prevent accidental partial matches (e.g. src/Foo vs src/FooBar).
     */
    public function isInSourceScope(string $absPath): bool
    {
        if (!$this->hasSourceScope()) {
            return true;
        }

        $normalized = $this->normalize($absPath);

        // Exclude wins.
        foreach ($this->sourceExcludeFiles as $excludedFile) {
            if ($normalized === $this->normalize($excludedFile)) {
                return false;
            }
        }
        foreach ($this->sourceExcludeDirectories as $excludedDir) {
            $excludedNorm = $this->normalize($excludedDir);
            if (str_starts_with($normalized, $excludedNorm.'/') || $normalized === $excludedNorm) {
                return false;
            }
        }

        // If no include configured, everything that isn't excluded is in scope.
        if ([] === $this->sourceIncludeDirectories && [] === $this->sourceIncludeFiles) {
            return true;
        }

        // Otherwise: must sit under an include directory or equal an include file.
        foreach ($this->sourceIncludeFiles as $includedFile) {
            if ($normalized === $this->normalize($includedFile)) {
                return true;
            }
        }
        foreach ($this->sourceIncludeDirectories as $includedDir) {
            $includedNorm = $this->normalize($includedDir);
            if (str_starts_with($normalized, $includedNorm.'/') || $normalized === $includedNorm) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
