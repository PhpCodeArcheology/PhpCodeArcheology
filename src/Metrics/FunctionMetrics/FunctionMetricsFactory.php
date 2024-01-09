<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\FunctionMetrics;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\MetricsInterface;

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
