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
        $classes = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $methods = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        $classMethods = [];
        foreach ($classes as $classKey => $_) {
            $methodKeyNamePairs = $this->metricsController->getCollectionByIdentifierString(
                $classKey,
                'methods'
            )?->getAsArray() ?? [];

            $classMethods[$classKey] = array_intersect_key($methods, $methodKeyNamePairs);
        }

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $methodListMetrics = $this->metricsController->getListMetricsByCollectionType(
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

    /**
     * @param $methodCollection
     * @param array $methods
     * @param $class
     * @return array
     */
    function getMethods(array $methodCollection, array $methods): array
    {
        $methodKeys = array_keys($methodCollection);
        $methodListMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );
        $methodData = array_filter($methods, function ($key) use ($methodKeys) {
            return in_array($key, $methodKeys);
        }, ARRAY_FILTER_USE_KEY);
        array_walk($methodData, function (&$method, $key) {
            $parameterCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'parameters'
            )->getAsArray();
            $method['parameterCount'] = count($parameterCollection);
        });
        $methodData = $this->setDataFromMetricTypesAndArrayToArrayKey($methodData, $methodListMetrics, 'listData');

        $methodTableHeaders = array_map(function ($metricType) {
            return $metricType->__toArray();
        }, $methodListMetrics);

        return array($methodData, $methodTableHeaders);
    }
}
