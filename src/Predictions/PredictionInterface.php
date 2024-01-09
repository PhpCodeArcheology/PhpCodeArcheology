<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Metrics;

interface PredictionInterface
{
    const INFO = 0;
    const WARNING = 1;
    const ERROR = 2;

    public function predict(Metrics $metrics): int;

    public function getLevel(): int;
}
