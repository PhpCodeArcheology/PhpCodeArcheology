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
        $classes = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $methods = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        $classMethods = [];
        foreach ($classes as $classKey => $_) {
            $methodKeyNamePairs = $this->repository->loadCollection(
                null,
                $classKey,
                'methods'
            )?->getAsArray() ?? [];

            $classMethods[$classKey] = array_intersect_key($methods, $methodKeyNamePairs);
        }

        $listMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $detailMetrics = $this->repository->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $methodListMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $templateData = [
            'classes' => $classes,
            'methods' => $classMethods,
            'tableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $listMetrics),
            'methodTableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $methodListMetrics),
            'listMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $listMetrics),
            'detailMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $detailMetrics),
            'methodListMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $methodListMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }

}
