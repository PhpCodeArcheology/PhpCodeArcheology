<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FunctionDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $functions = $this->reportDataContainer->get('functions')->getAll();

        array_walk($functions, function(&$function, $key) {
            $parameterCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'parameters'
            )->getAsArray();

            $dependencies = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'dependencies'
            )?->getAsArray();


            $function['parameterCount'] = count($parameterCollection);
            $function['parameters'] = $parameterCollection;
            $function['dependencies'] = $dependencies;
        });

        $methods = array_filter($functions, function($function) {
            return $function['functionType']->getValue() === 'method';
        });

        $functions = array_filter($functions, function($function) {
            return $function['functionType']->getValue() === 'function';
        });

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $functions = $this->setDataFromMetricTypesAndArrayToArrayKey($functions, $detailMetrics, 'detailData');
        $functions = $this->setDataFromMetricTypesAndArrayToArrayKey($functions, $listMetrics, 'listData');

        $methodListMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $methodDetailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $methods = $this->setDataFromMetricTypesAndArrayToArrayKey($methods, $methodDetailMetrics, 'detailData');
        $methods = $this->setDataFromMetricTypesAndArrayToArrayKey($methods, $methodListMetrics, 'listData');

        $this->templateData['functions'] = $functions;
        $this->templateData['methods'] = $methods;

        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);
    }
}