<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;

use Marcus\PhpLegacyAnalyzer\Analysis\ClassName;
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
