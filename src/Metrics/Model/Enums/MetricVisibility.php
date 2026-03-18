<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Enums;

enum MetricVisibility: int
{
    case ShowDetails = 0;
    case ShowList = 1;
    case ShowEverywhere = 2;
    case ShowNowhere = 3;
    case ShowCoupling = 4;
}
