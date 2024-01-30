<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\FunctionMetrics;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class FunctionMetricsFactory
{
    public static function createFromMetricsByNameAndPath(
        MetricsContainer $metrics,
        mixed            $name,
        mixed            $path): MetricsCollectionInterface
    {
        $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $name, (string) $path);

        return $metrics->get($functionId);
    }

    public static function createFromMethodsByNameAndClassMetrics(
        array                      $methods,
        mixed                      $name,
        MetricsCollectionInterface $classMetrics): FunctionMetricsCollection
    {
        $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
            (string) $name,
            (string) $classMetrics->getIdentifier()
        );

        return $methods[$methodId];
    }
}
