<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
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
            if (($collection->get('interface')?->getValue() ?? false)
                || ($collection->get('trait')?->getValue() ?? false)
                || ($collection->get('enum')?->getValue() ?? false)) {
                continue;
            }

            $hasTest = $collection->get('hasTest')?->getValue() ?? false;
            if ($hasTest) {
                continue;
            }

            $coverageGaps[] = [
                'id' => (string) $collection->getIdentifier(),
                'name' => $collection->get('singleName')?->getValue() ?? '',
                'fullName' => $collection->get('fullName')?->getValue() ?? '',
                'cc' => $collection->get('cc')?->getValue() ?? 0,
                'lloc' => $collection->get('lloc')?->getValue() ?? 0,
                'refactoringPriority' => $collection->get('refactoringPriority')?->getValue() ?? 0.0,
                'lineCoverage' => $collection->get('lineCoverage')?->getValue(),
            ];
        }

        usort($coverageGaps, fn($a, $b) => $b['cc'] <=> $a['cc']);

        $this->templateData['coverageGaps'] = array_slice($coverageGaps, 0, 20);

        $this->templateData['stats'] = [
            'testFileCount' => (int) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallTestFileCount'
            )?->getValue() ?? 0),
            'productionFileCount' => (int) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallProductionFileCount'
            )?->getValue() ?? 0),
            'testRatio' => (float) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallTestRatio'
            )?->getValue() ?? 0.0),
            'testedClassCount' => (int) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallTestedClassCount'
            )?->getValue() ?? 0),
            'untestedClassCount' => (int) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallUntestedClassCount'
            )?->getValue() ?? 0),
            'testedClassRatio' => (float) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallTestedClassRatio'
            )?->getValue() ?? 0.0),
            'functionBasedTestFileCount' => (int) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallFunctionBasedTestFileCount'
            )?->getValue() ?? 0),
            'detectedTestFrameworks' => (string) ($this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'detectedTestFrameworks'
            )?->getValue() ?? ''),
            'overallCoveragePercent' => $this->metricsController->getMetricValue(
                MetricCollectionTypeEnum::ProjectCollection, null, 'overallCoveragePercent'
            )?->getValue(),
        ];
    }
}
