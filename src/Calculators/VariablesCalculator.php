<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class VariablesCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    public function calculate(MetricsCollectionInterface $metrics): void
    {
    }

    public function afterTraverse(): void
    {
        $classes = $this->repository->loadCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        )->getAsArray();

        $files = $this->repository->loadCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'files'
        )->getAsArray();

        $functions = $this->repository->loadCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        )->getAsArray();

        $methods = $this->repository->loadCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        )->getAsArray();

        $elements = array_merge($classes, $files, $functions, $methods);

        foreach ($elements as $elementId => $elementName) {
            $classMetricCollection = $this->repository->getMetricCollection(null, $elementId);

            $superglobals = $classMetricCollection->get('superglobals')?->getValue() ?? [];
            $variables = $classMetricCollection->get('variables')?->getValue() ?? [];
            $constants = $classMetricCollection->get('constants')?->getValue() ?? [];

            $metricValues = [
                'superglobalsUsed' => array_sum($superglobals),
                'distinctSuperglobalsUsed' => count(
                    array_filter(
                        $superglobals,
                        fn($variableCount) => $variableCount > 0
                    )
                ),
                'variablesUsed' => array_sum($variables),
                'distinctVariablesUsed' => count($variables),
                'constantsUsed' => array_sum($constants),
                'distinctConstantsUsed' => count($constants),
            ];

            $metricValues['superglobalMetric'] = $metricValues['variablesUsed'] > 0 ?
                    round((($metricValues['superglobalsUsed'] + $metricValues['constantsUsed']) /
                            (
                                $metricValues['superglobalsUsed'] +
                                $metricValues['variablesUsed'] +
                                $metricValues['constantsUsed']
                            )
                        ) * 100,
                        2
                    )
                    : 0;

            $this->repository->saveMetricValues(null, $elementId, $metricValues);
        }
    }
}
