<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics;

enum MetricCollectionTypeEnum
{
    case ProjectCollection;
    case FileCollection;
    case ClassCollection;
    case FunctionCollection;
    case MethodCollection;
    case PackageCollection;
}
