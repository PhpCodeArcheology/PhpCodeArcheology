<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class TestsDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $coverageGaps = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            // Skip interfaces, traits, enums
            if ($collection->getBool(MetricKey::INTERFACE)
                || $collection->getBool(MetricKey::TRAIT)
                || $collection->getBool(MetricKey::ENUM)) {
                continue;
            }

            if ($collection->getBool(MetricKey::HAS_TEST)) {
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
            'testFileCount' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TEST_FILE_COUNT
            )?->asInt() ?? 0,
            'productionFileCount' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_PRODUCTION_FILE_COUNT
            )?->asInt() ?? 0,
            'testRatio' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TEST_RATIO
            )?->asFloat() ?? 0.0,
            'testedClassCount' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TESTED_CLASS_COUNT
            )?->asInt() ?? 0,
            'untestedClassCount' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_UNTESTED_CLASS_COUNT
            )?->asInt() ?? 0,
            'testedClassRatio' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_TESTED_CLASS_RATIO
            )?->asFloat() ?? 0.0,
            'functionBasedTestFileCount' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_FUNCTION_BASED_TEST_FILE_COUNT
            )?->asInt() ?? 0,
            'detectedTestFrameworks' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::DETECTED_TEST_FRAMEWORKS
            )?->asString() ?? '',
            'overallCoveragePercent' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_COVERAGE_PERCENT
            )?->getValue(),
        ];
    }
}
