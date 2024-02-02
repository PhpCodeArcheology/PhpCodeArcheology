<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FunctionDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $functions = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $methods = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        $parameters = [];
        $dependencies = [];

        $functionsAndMethods = array_merge($functions, $methods);

        array_walk($functionsAndMethods, function($function, $key) use (&$parameters, &$dependencies) {
            $parameterCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'parameters'
            )->getAsArray();

            $dependencyCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'dependencies'
            )?->getAsArray();


            $parameters[$key] = $parameterCollection;
            $dependencies[$key] = $dependencyCollection;
        });

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $methodListMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $methodDetailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $templateData = [
            'functions' => $functions,
            'methods' => $methods,
            'dependencies' => $dependencies,
            'parameters' => $parameters,
            'functionTableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $listMetrics),
            'methodTableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $methodListMetrics),
            'listMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $listMetrics),
            'functionDetailMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $detailMetrics),
            'methodDetailMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $methodDetailMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
