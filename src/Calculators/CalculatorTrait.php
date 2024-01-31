<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Repository\RepositoryInterface;

trait CalculatorTrait
{
    /**
     * @var string[] Das ist ein Test
     */
    private array $usedMetricTypes;

    public function __construct(
        private readonly RepositoryInterface $repository)
    {
    }
}
