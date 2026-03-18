<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Enums;

enum MetricValueType: int
{
    case Int = 0;
    case Float = 1;
    case String = 2;
    case Array = 3;
    case Percentage = 4;
    case Count = 5;
    case Bool = 6;
    case Storage = 7;
}
