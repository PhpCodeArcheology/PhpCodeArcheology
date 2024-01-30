<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricType;

class ClassCouplingDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;


    public function gatherData(): void
    {
        $classes = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $classes = array_filter($classes, function($class) {
            return $class->get('realClass')->getValue() === true;
        });

        $metrics = $this->metricsController->getMetricsByCollectionTypeAndVisibility(
            MetricCollectionTypeEnum::ClassCollection,
            MetricType::SHOW_COUPLING,
            false
        );

//        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $metrics, 'listData');

        $templateData = [
            'classes' => $classes,
            'tableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $metrics),
             'listMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $metrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
