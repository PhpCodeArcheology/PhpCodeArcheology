<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class TestsDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    /**
     * Minimum cyclomatic complexity for a class to appear in the
     * "Untested Complex Classes" table.
     *
     * Matches the default of `untestedComplexCode.cc` used by
     * UntestedComplexCodePrediction, so the HTML list and the problem count
     * stay consistent. Trivial classes (exceptions, one-liner DTOs, …)
     * don't need their own tests and are filtered out here.
     */
    private const MIN_COMPLEXITY_FOR_GAP = 8;

    public function gatherData(): void
    {
        $coverageGaps = [];

        foreach ($this->registry->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            // Skip interfaces, traits, enums
            if ($collection->getBool(MetricKey::INTERFACE)
                || $collection->getBool(MetricKey::TRAIT)
                || $collection->getBool(MetricKey::ENUM)) {
                continue;
            }

            // Skip classes outside phpunit.xml's <source> coverage scope — these are
            // infrastructure code (fixtures, kernel, migrations) and should not appear
            // in the untested gap list.
            if ($collection->getBool(MetricKey::EXCLUDED_BY_PHPUNIT_SOURCE)) {
                continue;
            }

            if ($collection->getBool(MetricKey::HAS_TEST)) {
                continue;
            }

            // The table is titled "Untested Complex Classes" — honor the "complex" part.
            // Trivial classes (exceptions with CC=1, one-liner DTOs, …) are not
            // meaningful coverage gaps and would only dilute the list.
            if ($collection->getInt(MetricKey::CC) < self::MIN_COMPLEXITY_FOR_GAP) {
                continue;
            }

            $coverageGaps[] = [
                'id' => (string) $collection->getIdentifier(),
                'name' => $collection->getString(MetricKey::SINGLE_NAME),
                'fullName' => $collection->getString(MetricKey::FULL_NAME),
                'cc' => $collection->getInt(MetricKey::CC),
                'lloc' => $collection->getInt(MetricKey::LLOC),
                'refactoringPriority' => $collection->getFloat(MetricKey::REFACTORING_PRIORITY),
                'lineCoverage' => $collection->get(MetricKey::LINE_COVERAGE)?->getValue(),
            ];
        }

        usort($coverageGaps, fn (array $a, array $b): int => $b['cc'] <=> $a['cc']);

        $this->templateData['coverageGaps'] = array_slice($coverageGaps, 0, 20);

        $this->templateData['stats'] = [
            'testFileCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TEST_FILE_COUNT
            )?->asInt() ?? 0,
            'productionFileCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_PRODUCTION_FILE_COUNT
            )?->asInt() ?? 0,
            'testRatio' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TEST_RATIO
            )?->asFloat() ?? 0.0,
            'testedClassCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TESTED_CLASS_COUNT
            )?->asInt() ?? 0,
            'untestedClassCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_UNTESTED_CLASS_COUNT
            )?->asInt() ?? 0,
            'testedClassRatio' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TESTED_CLASS_RATIO
            )?->asFloat() ?? 0.0,
            'functionBasedTestFileCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_FUNCTION_BASED_TEST_FILE_COUNT
            )?->asInt() ?? 0,
            'sourceExcludedClassCount' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_SOURCE_EXCLUDED_CLASS_COUNT
            )?->asInt() ?? 0,
            'detectedTestFrameworks' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::DETECTED_TEST_FRAMEWORKS
            )?->asString() ?? '',
            'overallCoveragePercent' => $this->reader->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_COVERAGE_PERCENT
            )?->getValue(),
        ];
    }
}
