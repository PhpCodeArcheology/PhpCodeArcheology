<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FunctionDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $functions = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $methods = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        $parameters = [];
        $dependencies = [];

        $functionsAndMethods = array_merge($functions, $methods);

        array_walk($functionsAndMethods, function($function, $key) use (&$parameters, &$dependencies) {
            $parameterCollection = $this->repository->loadCollection(
                null,
                $key,
                'parameters'
            )->getAsArray();

            $dependencyCollection = $this->repository->loadCollection(
                null,
                $key,
                'dependencies'
            )?->getAsArray();


            $parameters[$key] = $parameterCollection;
            $dependencies[$key] = $dependencyCollection;
        });

        $listMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $detailMetrics = $this->repository->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $methodListMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $methodDetailMetrics = $this->repository->getDetailMetricsByCollectionType(
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
