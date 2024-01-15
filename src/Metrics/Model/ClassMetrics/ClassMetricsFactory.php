<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\ClassMetrics;

use PhpCodeArch\Analysis\ClassName;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpParser\Node;

class ClassMetricsFactory
{
    public static function createFromMetricsByNodeAndPath(
        MetricsContainer $metrics,
        Node             $node,
        mixed            $path): ClassMetricsCollection
    {
        $className = (string) ClassName::ofNode($node);
        $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, (string) $path);

        return $metrics->get($classId);
    }

}
