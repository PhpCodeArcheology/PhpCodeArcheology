<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpParser\NodeTraverser;
use PhpParser\Parser;

final readonly class TestCoversParser
{
    private const EXCLUDED_PREFIXES = [
        'PHPUnit\\', 'Pest\\', 'Codeception\\',
        'Mockery\\', 'Prophecy\\',
    ];

    private const EXCLUDED_SUFFIXES = ['TestCase', 'MockObject'];

    public function __construct(private Parser $parser)
    {
    }

    /**
     * Parse test files to extract @covers annotations and use-statements.
     *
     * @param string[] $testFiles Absolute paths of class-based test files
     */
    public function parse(array $testFiles): TestCoversParseResult
    {
        $coversMap = [];
        $useStatementsMap = [];

        foreach ($testFiles as $file) {
            $code = @file_get_contents($file);
            if (false === $code) {
                continue;
            }

            try {
                $ast = $this->parser->parse($code);
            } catch (\PhpParser\Error) {
                continue;
            }

            if (null === $ast) {
                continue;
            }

            $visitor = new TestCoversVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $covers = $visitor->getCovers();
            if ([] !== $covers) {
                $coversMap[$file] = $covers;
            }

            $filtered = $this->filterProductionUses($visitor->getUseStatements());
            if ([] !== $filtered) {
                $useStatementsMap[$file] = $filtered;
            }
        }

        return new TestCoversParseResult($coversMap, $useStatementsMap);
    }

    /**
     * Filter out test infrastructure imports, keeping only production class references.
     *
     * @param string[] $fqcns
     *
     * @return string[]
     */
    private function filterProductionUses(array $fqcns): array
    {
        $result = [];
        foreach ($fqcns as $fqcn) {
            $excluded = false;
            foreach (self::EXCLUDED_PREFIXES as $prefix) {
                if (str_starts_with($fqcn, $prefix)) {
                    $excluded = true;
                    break;
                }
            }
            if (!$excluded) {
                $shortName = basename(str_replace('\\', '/', $fqcn));
                foreach (self::EXCLUDED_SUFFIXES as $suffix) {
                    if (str_ends_with($shortName, $suffix)) {
                        $excluded = true;
                        break;
                    }
                }
            }
            if (!$excluded) {
                $result[] = $fqcn;
            }
        }

        return $result;
    }
}
