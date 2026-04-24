<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;

class ProjectDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $projectMetrics = $this->reader->getMetricCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null
        );

        $metricTypes = $this->registry->getMetricsByCollectionTypeAndVisibility(
            MetricCollectionTypeEnum::ProjectCollection,
            MetricVisibility::ShowEverywhere
        );

        $data = [];
        foreach ($metricTypes as $metricType) {
            $value = $projectMetrics->get($metricType->getKey());
            if (null === $value) {
                continue;
            }
            $data[$metricType->getKey()] = $value;
        }

        $this->templateData['elements'] = $data;
    }
}
