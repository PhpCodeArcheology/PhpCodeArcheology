<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
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

        // Build className → classId map from method classInfo
        $classNameToId = [];
        foreach ($methods as $method) {
            $classInfo = $method->get('classInfo')?->getValue() ?? [];
            if (isset($classInfo['name'], $classInfo['id'])) {
                $classNameToId[$classInfo['name']] = $classInfo['id'];
            }
        }

        $parameters = [];
        $dependencies = [];
        $methodCalls = [];

        $functionsAndMethods = array_merge($functions, $methods);

        array_walk($functionsAndMethods, function($function, $key) use (&$parameters, &$dependencies, &$methodCalls, $classNameToId) {
            $parameterCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'parameters'
            )->getAsArray();

            $dependencyCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'dependencies'
            )?->getAsArray();

            $methodCallsCollection = $this->metricsController->getCollectionByIdentifierString(
                $key,
                'methodCalls'
            )?->getAsArray();

            // Enrich method calls with target IDs for linking
            $enrichedCalls = null;
            if ($methodCallsCollection !== null) {
                $enrichedCalls = [];
                foreach ($methodCallsCollection as $call) {
                    $call['targetMethodId'] = (string) FunctionAndClassIdentifier::ofNameAndPath(
                        $call['targetMethod'],
                        $call['targetClass']
                    );
                    $call['targetClassId'] = $classNameToId[$call['targetClass']] ?? '';
                    $enrichedCalls[] = $call;
                }
            }

            $parameters[$key] = $parameterCollection;
            $dependencies[$key] = $dependencyCollection;
            $methodCalls[$key] = $enrichedCalls;
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

        // Build reverse index: calledBy[targetMethodId][] = ['sourceClass' => ..., 'sourceMethod' => ...]
        $calledBy = [];
        foreach ($methods as $sourceMethodId => $sourceMethod) {
            $calls = $methodCalls[$sourceMethodId] ?? null;
            if ($calls === null) {
                continue;
            }

            $classInfo = $sourceMethod->get('classInfo')?->getValue() ?? [];
            $sourceClassName = $classInfo['name'] ?? '';
            $sourceClassId = $classInfo['id'] ?? '';
            $sourceMethodName = $sourceMethod->getName();

            $seen = [];
            foreach ($calls as $call) {
                $targetMethodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $call['targetMethod'],
                    $call['targetClass']
                );
                $targetClassId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $call['targetClass'],
                    $sourceMethod->getPath()
                );

                $callKey = $sourceClassName . '::' . $sourceMethodName;
                if (isset($seen[$targetMethodId . $callKey])) {
                    continue;
                }
                $seen[$targetMethodId . $callKey] = true;

                $calledBy[$targetMethodId][] = [
                    'sourceClass' => $sourceClassName,
                    'sourceClassId' => $sourceClassId,
                    'sourceMethod' => $sourceMethodName,
                    'sourceMethodId' => $sourceMethodId,
                ];
            }
        }

        $templateData = [
            'functions' => $functions,
            'methods' => $methods,
            'dependencies' => $dependencies,
            'parameters' => $parameters,
            'methodCalls' => $methodCalls,
            'calledBy' => $calledBy,
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
