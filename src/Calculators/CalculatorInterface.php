<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

interface CalculatorInterface
{
    public function calculate(MetricsCollectionInterface $metrics);
}
