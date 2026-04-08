<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class PhpunitTestSuite
{
    /**
     * @param PhpunitTestSuiteDirectory[] $directories
     * @param string[]                    $files         Absolute paths of explicit <file> entries
     * @param string[]                    $excludedPaths Absolute paths of <exclude> entries (file or directory)
     */
    public function __construct(
        public string $name,
        public array $directories = [],
        public array $files = [],
        public array $excludedPaths = [],
    ) {
    }
}
