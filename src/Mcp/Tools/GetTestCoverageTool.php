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
            $stats = $this->extractCoverageStats($rawData);

            if (0 === $stats['testFileCount'] && '' === $stats['frameworks']) {
                return implode("\n", [
                    'Test Coverage Summary',
                    '=====================',
                    'No test infrastructure detected. No test framework found in composer.json and no test directories found.',
                ]);
            }

            $lines = $this->buildCoverageSummaryHeader($stats);
            $rawGaps = $rawData['coverageGaps'] ?? null;
            $gaps = is_array($rawGaps) ? $rawGaps : [];
            $lines = array_merge($lines, $this->formatCoverageGaps($gaps, $limit));

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving test coverage.';
        }
    }

    /**
     * @param array<string, mixed> $rawData
     *
     * @return array{testFileCount: int, frameworks: string, productionFileCount: int, testRatio: float, testedClassCount: int, untestedClassCount: int, totalClasses: int, testedClassRatio: float, functionBasedCount: int, coveragePercent: float|null}
     */
    private function extractCoverageStats(array $rawData): array
    {
        $rawStats = $rawData['stats'] ?? null;
        $stats = is_array($rawStats) ? $rawStats : [];

        $testedClassCount = is_int($stats['testedClassCount'] ?? null) ? $stats['testedClassCount'] : 0;
        $untestedClassCount = is_int($stats['untestedClassCount'] ?? null) ? $stats['untestedClassCount'] : 0;
        $rawCoveragePercent = $stats['overallCoveragePercent'] ?? null;

        return [
            'testFileCount' => is_int($stats['testFileCount'] ?? null) ? $stats['testFileCount'] : 0,
            'frameworks' => is_string($stats['detectedTestFrameworks'] ?? null) ? $stats['detectedTestFrameworks'] : '',
            'productionFileCount' => is_int($stats['productionFileCount'] ?? null) ? $stats['productionFileCount'] : 0,
            'testRatio' => round(is_numeric($stats['testRatio'] ?? null) ? (float) $stats['testRatio'] : 0.0, 1),
            'testedClassCount' => $testedClassCount,
            'untestedClassCount' => $untestedClassCount,
            'totalClasses' => $testedClassCount + $untestedClassCount,
            'testedClassRatio' => round(is_numeric($stats['testedClassRatio'] ?? null) ? (float) $stats['testedClassRatio'] : 0.0, 1),
            'functionBasedCount' => is_int($stats['functionBasedTestFileCount'] ?? null) ? $stats['functionBasedTestFileCount'] : 0,
            'coveragePercent' => is_numeric($rawCoveragePercent) ? (float) $rawCoveragePercent : null,
        ];
    }

    /**
     * @param array{testFileCount: int, frameworks: string, productionFileCount: int, testRatio: float, testedClassCount: int, untestedClassCount: int, totalClasses: int, testedClassRatio: float, functionBasedCount: int, coveragePercent: float|null} $stats
     *
     * @return list<string>
     */
    private function buildCoverageSummaryHeader(array $stats): array
    {
        $lines = ['Test Coverage Summary', '====================='];

        if ('' !== $stats['frameworks']) {
            $lines[] = "Test Frameworks: {$stats['frameworks']}";
        }

        if (null !== $stats['coveragePercent']) {
            $lines[] = 'Line Coverage: '.round($stats['coveragePercent'], 1).'% (from Clover XML)';
        }

        $lines[] = "Tested Classes: {$stats['testedClassCount']} / {$stats['totalClasses']} ({$stats['testedClassRatio']}%)";
        $lines[] = "Test Files: {$stats['testFileCount']} ({$stats['functionBasedCount']} function-based)";

        if ($stats['functionBasedCount'] > 0) {
            $frameworkHint = '' !== $stats['frameworks']
                ? " ({$stats['frameworks']}-style, not mapped to classes)"
                : ' (function-based, not mapped to classes)';
            $lines[] = "Function-based Tests: {$stats['functionBasedCount']} files{$frameworkHint}";
        }

        return $lines;
    }

    /**
     * @param array<mixed> $gaps
     *
     * @return list<string>
     */
    private function formatCoverageGaps(array $gaps, int $limit): array
    {
        $gaps = array_slice($gaps, 0, max(1, $limit));

        if ([] === $gaps) {
            return ['', 'No untested classes found.'];
        }

        $actualCount = count($gaps);
        $lines = [
            '',
            "Top {$actualCount} Untested Complex Classes:",
            sprintf(' %-3s %-38s %4s %6s  %8s', '#', 'Class', 'CC', 'LLOC', 'Priority'),
            str_repeat('-', 68),
        ];

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
            $refPriority = is_numeric($gap['refactoringPriority'] ?? null) ? (float) $gap['refactoringPriority'] : 0.0;
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

        return $lines;
    }
}
