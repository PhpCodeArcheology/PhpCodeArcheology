<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Enums;

enum BetterDirection: int
{
    case Irrelevant = 0;
    case High = 1;
    case Low = 2;
}
