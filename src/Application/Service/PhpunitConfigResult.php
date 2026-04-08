<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class PhpunitConfigResult
{
    /**
     * @param PhpunitTestSuite[] $testSuites
     */
    public function __construct(
        public bool $found = false,
        public string $configFilePath = '',
        public array $testSuites = [],
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

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
