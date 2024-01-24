<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;

class ClassDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $classes;

    public function gatherData(): void
    {
        $classes = $this->reportDataContainer->get('classes')->getAll();
        $methods = $this->reportDataContainer->get('functions')->getAll();

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $detailMetrics, 'detailData');
        $classes = $this->setDataFromMetricTypesAndArrayToArrayKey($classes, $listMetrics, 'listData');

        array_walk($classes, function(&$class, $classId) use ($methods) {
            $methodCollection = $this->metricsController->getCollectionByIdentifierString(
                $classId,
                'methods'
            )->getAsArray();

            list($methodData, $methodTableHeaders) = $this->getMethods($methodCollection, $methods);

            $collectionKeys = [
                'dependencies',
                'usedClasses',
                'traits',
                'interfaces',
                'extends',
            ];

            foreach ($collectionKeys as $collectionKey) {
                $class[$collectionKey] = $this->metricsController->getCollectionByIdentifierString(
                    $classId,
                    $collectionKey
                )->getAsArray();;
            }

            $class['methods'] = $methodData;
            $class['methodTableHeaders'] = $methodTableHeaders;
        });

        $this->templateData['classes'] = $classes;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);

        $this->classes = $classes;
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
