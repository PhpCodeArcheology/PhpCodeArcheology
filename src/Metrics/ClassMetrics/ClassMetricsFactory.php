<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\ClassMetrics;

use PhpCodeArch\Analysis\ClassName;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Metrics;
use PhpParser\Node;

class ClassMetricsFactory
{
    public static function createFromMetricsByNodeAndPath(
        Metrics $metrics,
        Node $node,
        mixed $path): ClassMetrics
    {
        $className = (string) ClassName::ofNode($node);
        $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, (string) $path);

        return $metrics->get($classId);
    }

}