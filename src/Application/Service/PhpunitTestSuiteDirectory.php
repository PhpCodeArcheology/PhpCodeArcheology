<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class PhpunitTestSuiteDirectory
{
    public function __construct(
        public string $absolutePath,
        public string $suffix = 'Test.php',
        public string $prefix = '',
    ) {
    }
}
