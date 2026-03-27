<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\FunctionMetrics;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;

class FunctionMetricsFactory
{
    public static function createFromMetricsByNameAndPath(
        MetricsContainer $metrics,
        string $name,
        string $path): MetricsCollectionInterface
    {
        $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath($name, $path);

        return $metrics->get($functionId);
    }

    /**
     * @param array<string, FunctionMetricsCollection> $methods
     */
    public static function createFromMethodsByNameAndClassMetrics(
        array $methods,
        string $name,
        MetricsCollectionInterface $classMetrics): FunctionMetricsCollection
    {
        $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
            $name,
            (string) $classMetrics->getIdentifier()
        );

        return $methods[$methodId];
    }
}
