<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class ClassDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $classes;

    public function gatherData(): void
    {
        $classes = $this->reportDataContainer->get('classes')->getAll();

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $detailMetrics, 'detailData');
        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $listMetrics, 'listData');

//        array_walk($classes, function(&$class) {
//            $class['methods'] = array_map(fn($methodMetric) => $methodMetric->getAll(), $class['methods']);
//        });

        $this->templateData['classes'] = $classes;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);

        $this->classes = $classes;
    }
}
