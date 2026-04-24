<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class VariablesCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    public function calculate(MetricsCollectionInterface $metrics): void
    {
    }

    public function afterTraverse(): void
    {
        $classes = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        )?->getAsArray() ?? [];

        $files = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'files'
        )?->getAsArray() ?? [];

        $functions = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        )?->getAsArray() ?? [];

        $methods = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods'
        )?->getAsArray() ?? [];

        $elements = array_merge($classes, $files, $functions, $methods);

        foreach (array_keys($elements) as $elementId) {
            $classMetricCollection = $this->reader->getMetricCollectionByIdentifierString($elementId);

            $superglobals = $classMetricCollection->getArray(MetricKey::SUPERGLOBALS);
            $variables = $classMetricCollection->getArray(MetricKey::VARIABLES);
            $constants = $classMetricCollection->getArray(MetricKey::CONSTANTS);

            $metricValues = [
                MetricKey::SUPERGLOBALS_USED => array_sum($superglobals),
                MetricKey::DISTINCT_SUPERGLOBALS_USED => count(
                    array_filter(
                        $superglobals,
                        fn ($variableCount): bool => $variableCount > 0
                    )
                ),
                MetricKey::VARIABLES_USED => array_sum($variables),
                MetricKey::DISTINCT_VARIABLES_USED => count($variables),
                MetricKey::CONSTANTS_USED => array_sum($constants),
                MetricKey::DISTINCT_CONSTANTS_USED => count($constants),
            ];

            $metricValues[MetricKey::SUPERGLOBAL_METRIC] = ($metricValues[MetricKey::SUPERGLOBALS_USED] + $metricValues[MetricKey::VARIABLES_USED] + $metricValues[MetricKey::CONSTANTS_USED]) > 0 ?
                    round((($metricValues[MetricKey::SUPERGLOBALS_USED] + $metricValues[MetricKey::CONSTANTS_USED]) /
                            (
                                $metricValues[MetricKey::SUPERGLOBALS_USED] +
                                $metricValues[MetricKey::VARIABLES_USED] +
                                $metricValues[MetricKey::CONSTANTS_USED]
                            )
                    ) * 100,
                        2
                    )
                    : 0;

            $this->writer->setMetricValuesByIdentifierString($elementId, $metricValues);
        }
    }
}
