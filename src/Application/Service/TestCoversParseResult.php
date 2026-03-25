<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class TestCoversParseResult
{
    public function __construct(
        /** @var array<string, string[]> testFile path → covered FQCNs from @covers/@coversClass */
        public array $coversMap = [],
        /** @var array<string, string[]> testFile path → production FQCNs from filtered use-statements */
        public array $useStatementsMap = [],
    ) {
    }
}
