<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $files;

    public function gatherData(): void
    {
        $files = $this->reportDataContainer->get('files')->getAll();

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $files = $this->setDataFromMetricTypesAndArrayToArrayKey($files, $detailMetrics, 'detailData');
        $files = $this->setDataFromMetricTypesAndArrayToArrayKey($files, $listMetrics, 'listData');

        $this->templateData['files'] = $files;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);

        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
