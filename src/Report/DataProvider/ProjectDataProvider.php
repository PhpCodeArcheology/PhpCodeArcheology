<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricType;

class ProjectDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    function gatherData(): void
    {
        $projectMetrics = $this->metricsController->getMetricCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null
        );

        $metricTypes = $this->metricsController->getMetricsByCollectionTypeAndVisibility(
            MetricCollectionTypeEnum::ProjectCollection,
            MetricType::SHOW_EVERYWHERE
        );

        $data = [];
        foreach ($metricTypes as $metricType) {
            $value = $projectMetrics->get($metricType->getKey());
            $data[$metricType->getKey()] = $value;
        }

        $this->templateData['elements'] = $data;
    }
}
