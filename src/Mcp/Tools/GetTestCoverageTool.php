<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class GetTestCoverageTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getTestCoverage(int $limit = 10): string
    {
        try {
            $rawData = $this->factory->getTestsDataProvider()->getTemplateData();
            $rawStats = $rawData['stats'] ?? null;
            $stats = is_array($rawStats) ? $rawStats : [];
            $rawGaps = $rawData['coverageGaps'] ?? null;
            $gaps = is_array($rawGaps) ? $rawGaps : [];

            $testFileCount = is_int($stats['testFileCount'] ?? null) ? $stats['testFileCount'] : 0;
            $rawFrameworks = $stats['detectedTestFrameworks'] ?? null;
            $frameworks = is_string($rawFrameworks) ? $rawFrameworks : '';

            if (0 === $testFileCount && '' === $frameworks) {
                return implode("\n", [
                    'Test Coverage Summary',
                    '=====================',
                    'No test infrastructure detected. No test framework found in composer.json and no test directories found.',
                ]);
            }

            $productionFileCount = is_int($stats['productionFileCount'] ?? null) ? $stats['productionFileCount'] : 0;
            $rawTestRatio = $stats['testRatio'] ?? null;
            $testRatio = round(is_numeric($rawTestRatio) ? (float) $rawTestRatio : 0.0, 1);
            $testedClassCount = is_int($stats['testedClassCount'] ?? null) ? $stats['testedClassCount'] : 0;
            $untestedClassCount = is_int($stats['untestedClassCount'] ?? null) ? $stats['untestedClassCount'] : 0;
            $totalClasses = $testedClassCount + $untestedClassCount;
            $rawTestedClassRatio = $stats['testedClassRatio'] ?? null;
            $testedClassRatio = round(is_numeric($rawTestedClassRatio) ? (float) $rawTestedClassRatio : 0.0, 1);
            $functionBasedCount = is_int($stats['functionBasedTestFileCount'] ?? null) ? $stats['functionBasedTestFileCount'] : 0;

            $lines = [
                'Test Coverage Summary',
                '=====================',
            ];

            if ('' !== $frameworks) {
                $lines[] = "Test Frameworks: {$frameworks}";
            }

            $rawCoveragePercent = $stats['overallCoveragePercent'] ?? null;
            $coveragePercent = is_numeric($rawCoveragePercent) ? (float) $rawCoveragePercent : null;
            if (null !== $coveragePercent) {
                $lines[] = 'Line Coverage: '.round($coveragePercent, 1).'% (from Clover XML)';
            }

            $lines[] = "Test Ratio: {$testRatio}% ({$testFileCount} test files / {$productionFileCount} production files)";
            $lines[] = "Tested Classes: {$testedClassCount} / {$totalClasses} ({$testedClassRatio}%)";

            if ($functionBasedCount > 0) {
                $frameworkHint = '' !== $frameworks ? " ({$frameworks}-style, not mapped to classes)" : ' (function-based, not mapped to classes)';
                $lines[] = "Function-based Tests: {$functionBasedCount} files{$frameworkHint}";
            }

            $gaps = array_slice($gaps, 0, max(1, $limit));

            if ([] !== $gaps) {
                $actualCount = count($gaps);
                $lines[] = '';
                $lines[] = "Top {$actualCount} Untested Complex Classes:";
                $lines[] = sprintf(' %-3s %-38s %4s %6s  %8s', '#', 'Class', 'CC', 'LLOC', 'Priority');
                $lines[] = str_repeat('-', 68);

                foreach ($gaps as $i => $gap) {
                    if (!is_array($gap)) {
                        continue;
                    }
                    $rawFullName = $gap['fullName'] ?? null;
                    $rawItemName = $gap['name'] ?? null;
                    $fullName = is_string($rawFullName) ? $rawFullName : '';
                    $itemName = is_string($rawItemName) ? $rawItemName : '';
                    $name = '' !== $fullName ? $fullName : $itemName;
                    $shortName = strlen($name) > 36 ? substr($name, 0, 33).'...' : $name;
                    $rawRefPriority = $gap['refactoringPriority'] ?? null;
                    $refPriority = is_numeric($rawRefPriority) ? (float) $rawRefPriority : 0.0;
                    $cc = is_int($gap['cc'] ?? null) ? $gap['cc'] : 0;
                    $lloc = is_int($gap['lloc'] ?? null) ? $gap['lloc'] : 0;
                    $lines[] = sprintf(' %-3d %-38s %4d %6d  %8.1f',
                        $i + 1,
                        $shortName,
                        $cc,
                        $lloc,
                        $refPriority
                    );
                }
            } else {
                $lines[] = '';
                $lines[] = 'No untested classes found.';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving test coverage.';
        }
    }
}
