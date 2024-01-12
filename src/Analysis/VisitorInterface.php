<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Manager\MetricType;
use PhpCodeArch\Metrics\Metrics;

interface VisitorInterface
{
    public function setPath(string $path): void;
}
