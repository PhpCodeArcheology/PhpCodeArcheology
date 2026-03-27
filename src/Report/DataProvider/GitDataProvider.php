<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;

class GitDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $hotspots = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof FileMetricsCollection) {
                continue;
            }

            $churn = $collection->getInt(MetricKey::GIT_CHURN_COUNT);
            $cc = $collection->getInt(MetricKey::CC);
            $loc = $collection->getInt(MetricKey::LOC);
            $fileName = $collection->getString(MetricKey::FILE_NAME);
            $dirName = $collection->getString(MetricKey::DIR_NAME);
            $authors = $collection->getInt(MetricKey::GIT_AUTHOR_COUNT);
            $ageDays = $collection->getInt(MetricKey::GIT_CODE_AGE_DAYS);

            if ($churn > 0 || $cc > 0) {
                $hotspots[] = [
                    'id' => (string) $collection->getIdentifier(),
                    'name' => $fileName,
                    'dir' => $dirName,
                    'churn' => $churn,
                    'cc' => $cc,
                    'loc' => $loc,
                    'authors' => $authors,
                    'ageDays' => $ageDays,
                ];
            }
        }

        // Sort by churn * cc (hotspot score) descending
        usort($hotspots, fn (array $a, array $b): int => (int) (($b['churn'] * $b['cc']) - ($a['churn'] * $a['cc'])));

        // Normalize churn to 0-1 range for "Change Frequency" axis
        $maxChurn = max(1, max(array_column($hotspots, 'churn') ?: [0]));
        foreach ($hotspots as &$hotspot) {
            $hotspot['changeFrequency'] = round($hotspot['churn'] / $maxChurn, 3);
        }
        unset($hotspot);

        $this->templateData['hotspots'] = $hotspots;
        $this->templateData['gitTotalCommits'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::GIT_TOTAL_COMMITS
        )?->asInt() ?? 0;
        $this->templateData['gitActiveAuthors'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::GIT_ACTIVE_AUTHORS
        )?->asInt() ?? 0;
        $this->templateData['gitAnalysisPeriod'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::GIT_ANALYSIS_PERIOD
        )?->asString() ?? 'N/A';
    }
}
