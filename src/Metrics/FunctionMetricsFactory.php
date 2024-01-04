<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;

class FunctionMetricsFactory
{
    public static function createFromMetricsByNameAndPath(
        Metrics $metrics,
        mixed $name,
        mixed $path): FunctionMetrics
    {
        $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $name, (string) $path);

        return $metrics->get($functionId);
    }

    public static function createFromMethodsByNameAndClassMetrics(
        array $methods,
        mixed $name,
        MetricsInterface $classMetrics): FunctionMetrics
    {
        $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
            (string) $name,
            (string) $classMetrics->getIdentifier()
        );

        return $methods[$methodId];
    }
}
