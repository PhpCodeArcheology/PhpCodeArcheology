<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
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

            $churn = $collection->get('gitChurnCount')?->getValue() ?? 0;
            $cc = $collection->get('cc')?->getValue() ?? 0;
            $loc = $collection->get('loc')?->getValue() ?? 0;
            $fileName = $collection->get('fileName')?->getValue() ?? '';
            $dirName = $collection->get('dirName')?->getValue() ?? '';
            $authors = $collection->get('gitAuthorCount')?->getValue() ?? 0;
            $ageDays = $collection->get('gitCodeAgeDays')?->getValue() ?? 0;

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
        usort($hotspots, fn($a, $b) => ($b['churn'] * $b['cc']) - ($a['churn'] * $a['cc']));

        $this->templateData['hotspots'] = $hotspots;
        $this->templateData['gitTotalCommits'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'gitTotalCommits'
        )?->getValue() ?? 0;
        $this->templateData['gitActiveAuthors'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'gitActiveAuthors'
        )?->getValue() ?? 0;
        $this->templateData['gitAnalysisPeriod'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'gitAnalysisPeriod'
        )?->getValue() ?? 'N/A';
    }
}
