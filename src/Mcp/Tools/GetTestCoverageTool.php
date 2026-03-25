<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class GetTestCoverageTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getTestCoverage(int $limit = 10): string
    {
        try {
            $data = $this->factory->getTestsDataProvider()->getTemplateData();
            $stats = $data['stats'] ?? [];
            $gaps = $data['coverageGaps'] ?? [];

            $testFileCount = $stats['testFileCount'] ?? 0;
            $frameworks = $stats['detectedTestFrameworks'] ?? '';

            if ($testFileCount === 0 && $frameworks === '') {
                return implode("\n", [
                    "Test Coverage Summary",
                    "=====================",
                    "No test infrastructure detected. No test framework found in composer.json and no test directories found.",
                ]);
            }

            $productionFileCount = $stats['productionFileCount'] ?? 0;
            $testRatio = round((float) ($stats['testRatio'] ?? 0.0), 1);
            $testedClassCount = $stats['testedClassCount'] ?? 0;
            $untestedClassCount = $stats['untestedClassCount'] ?? 0;
            $totalClasses = $testedClassCount + $untestedClassCount;
            $testedClassRatio = round((float) ($stats['testedClassRatio'] ?? 0.0), 1);
            $functionBasedCount = $stats['functionBasedTestFileCount'] ?? 0;

            $lines = [
                "Test Coverage Summary",
                "=====================",
            ];

            if ($frameworks !== '') {
                $lines[] = "Test Frameworks: {$frameworks}";
            }

            $coveragePercent = $stats['overallCoveragePercent'] ?? null;
            if ($coveragePercent !== null) {
                $lines[] = "Line Coverage: " . round((float) $coveragePercent, 1) . "% (from Clover XML)";
            }

            $lines[] = "Test Ratio: {$testRatio}% ({$testFileCount} test files / {$productionFileCount} production files)";
            $lines[] = "Tested Classes: {$testedClassCount} / {$totalClasses} ({$testedClassRatio}%)";

            if ($functionBasedCount > 0) {
                $frameworkHint = $frameworks !== '' ? " ({$frameworks}-style, not mapped to classes)" : " (function-based, not mapped to classes)";
                $lines[] = "Function-based Tests: {$functionBasedCount} files{$frameworkHint}";
            }

            $gaps = array_slice($gaps, 0, max(1, $limit));

            if (!empty($gaps)) {
                $actualCount = count($gaps);
                $lines[] = "";
                $lines[] = "Top {$actualCount} Untested Complex Classes:";
                $lines[] = sprintf(" %-3s %-38s %4s %6s  %8s", "#", "Class", "CC", "LLOC", "Priority");
                $lines[] = str_repeat("-", 68);

                foreach ($gaps as $i => $gap) {
                    $name = $gap['fullName'] !== '' ? $gap['fullName'] : $gap['name'];
                    $shortName = strlen($name) > 36 ? substr($name, 0, 33) . '...' : $name;
                    $lines[] = sprintf(" %-3d %-38s %4d %6d  %8.1f",
                        $i + 1,
                        $shortName,
                        $gap['cc'],
                        $gap['lloc'],
                        (float) $gap['refactoringPriority']
                    );
                }
            } else {
                $lines[] = "";
                $lines[] = "No untested classes found.";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error retrieving test coverage: " . $e->getMessage();
        }
    }
}
