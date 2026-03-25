<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class TestScanResult
{
    public function __construct(
        /** @var string[] Absolute paths of found test directories */
        public array $testDirectories = [],
        /** @var string[] Test files that contain a class declaration */
        public array $classBasedTestFiles = [],
        /** @var string[] Test files without class declaration (Pest function-based) */
        public array $functionBasedTestFiles = [],
        /** @var array<string, string> Maps test file path → 'unit'|'integration'|'unknown' */
        public array $testFileToType = [],
    ) {
    }
}
