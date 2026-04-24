<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class ClassDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $classes = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $methods = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        $classMethods = [];
        foreach (array_keys($classes) as $classKey) {
            $methodKeyNamePairs = $this->reader->getCollectionByIdentifierString(
                $classKey,
                'methods'
            )?->getAsArray() ?? [];

            $classMethods[$classKey] = array_intersect_key($methods, $methodKeyNamePairs);
        }

        $listMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $detailMetrics = $this->registry->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $methodListMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $templateData = [
            'classes' => $classes,
            'methods' => $classMethods,
            'tableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $listMetrics),
            'methodTableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $methodListMetrics),
            'listMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $listMetrics),
            'detailMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $detailMetrics),
            'methodListMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $methodListMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
