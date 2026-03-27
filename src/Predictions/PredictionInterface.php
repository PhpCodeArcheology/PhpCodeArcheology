<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;

interface PredictionInterface
{
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    public function predict(MetricsController $metricsController): int;

    public function getLevel(): int;
}
