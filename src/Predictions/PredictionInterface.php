<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;

interface PredictionInterface
{
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;

    public function predict(MetricsController $metricsController): int;

    public function getLevel(): int;
}
