<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;

class ClassCouplingDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $classes = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $classes = array_filter($classes, fn ($class) => $class->getBool(MetricKey::REAL_CLASS));

        $metrics = $this->registry->getMetricsByCollectionTypeAndVisibility(
            MetricCollectionTypeEnum::ClassCollection,
            MetricVisibility::ShowCoupling,
            false
        );

        //        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $metrics, 'listData');

        $templateData = [
            'classes' => $classes,
            'tableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $metrics),
            'listMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $metrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
