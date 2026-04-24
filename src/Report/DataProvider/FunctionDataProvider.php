<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;

class FunctionDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $functions = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $methods = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        );

        // Build className → classId map from method classInfo
        $classNameToId = [];
        foreach ($methods as $method) {
            $classInfo = $method->getArray(MetricKey::CLASS_INFO);
            $className = $classInfo['name'] ?? null;
            $classId = $classInfo['id'] ?? null;
            if (is_string($className) && is_string($classId)) {
                $classNameToId[$className] = $classId;
            }
        }

        $parameters = [];
        $dependencies = [];
        $methodCalls = [];

        $functionsAndMethods = array_merge($functions, $methods);

        array_walk($functionsAndMethods, function ($function, string $key) use (&$parameters, &$dependencies, &$methodCalls, $classNameToId): void {
            $parameterCollection = $this->reader->getCollectionByIdentifierString(
                $key,
                'parameters'
            )?->getAsArray() ?? [];

            $dependencyCollection = $this->reader->getCollectionByIdentifierString(
                $key,
                'dependencies'
            )?->getAsArray();

            $methodCallsCollection = $this->reader->getCollectionByIdentifierString(
                $key,
                'methodCalls'
            )?->getAsArray();

            // Enrich method calls with target IDs for linking
            $enrichedCalls = null;
            if (null !== $methodCallsCollection) {
                $enrichedCalls = [];
                foreach ($methodCallsCollection as $call) {
                    if (!is_array($call)) {
                        continue;
                    }
                    $targetMethod = is_string($call['targetMethod'] ?? null) ? $call['targetMethod'] : '';
                    $targetClass = is_string($call['targetClass'] ?? null) ? $call['targetClass'] : '';
                    $call['targetMethodId'] = (string) FunctionAndClassIdentifier::ofNameAndPath(
                        $targetMethod,
                        $targetClass
                    );
                    $call['targetClassId'] = $classNameToId[$targetClass] ?? '';
                    $enrichedCalls[] = $call;
                }
            }

            $parameters[$key] = $parameterCollection;
            $dependencies[$key] = $dependencyCollection;
            $methodCalls[$key] = $enrichedCalls;
        });

        $listMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $detailMetrics = $this->registry->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $methodListMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        $methodDetailMetrics = $this->registry->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::MethodCollection
        );

        // Build reverse index: calledBy[targetMethodId][] = ['sourceClass' => ..., 'sourceMethod' => ...]
        $calledBy = [];
        foreach ($methods as $sourceMethodId => $sourceMethod) {
            $calls = $methodCalls[$sourceMethodId] ?? null;
            if (null === $calls) {
                continue;
            }

            $classInfo = $sourceMethod->getArray(MetricKey::CLASS_INFO);
            $rawSourceClassName = $classInfo['name'] ?? null;
            $sourceClassName = is_string($rawSourceClassName) ? $rawSourceClassName : '';
            $rawSourceClassId = $classInfo['id'] ?? null;
            $sourceClassId = is_string($rawSourceClassId) ? $rawSourceClassId : '';
            $sourceMethodName = $sourceMethod->getString(MetricKey::SINGLE_NAME);

            $seen = [];
            foreach ($calls as $call) {
                $callTargetMethod = is_string($call['targetMethod'] ?? null) ? $call['targetMethod'] : '';
                $callTargetClass = is_string($call['targetClass'] ?? null) ? $call['targetClass'] : '';
                $targetMethodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $callTargetMethod,
                    $callTargetClass
                );
                $targetClassId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $callTargetClass,
                    $sourceMethod->getPath()
                );

                $callKey = $sourceClassName.'::'.$sourceMethodName;
                if (isset($seen[$targetMethodId.$callKey])) {
                    continue;
                }
                $seen[$targetMethodId.$callKey] = true;

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
            'functionTableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $listMetrics),
            'methodTableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $methodListMetrics),
            'listMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $listMetrics),
            'functionDetailMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $detailMetrics),
            'methodDetailMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $methodDetailMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
