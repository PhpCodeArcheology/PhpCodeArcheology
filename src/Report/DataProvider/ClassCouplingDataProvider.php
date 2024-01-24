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
        $classes = $this->reportDataContainer->get('classes')->getAll();

        $classes = array_filter($classes, function($class) {
            return $class['realClass']->getValue() === true;
        });

        $metrics = $this->metricsController->getMetricsByCollectionTypeAndVisibility(
            MetricCollectionTypeEnum::ClassCollection,
            MetricType::SHOW_COUPLING,
            false
        );

        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $metrics, 'listData');

        $this->templateData['classes'] = $classes;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $metrics);

    }
}
