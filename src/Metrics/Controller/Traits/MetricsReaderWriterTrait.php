<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller\Traits;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;

/**
 * Shared constructor for Calculators and Predictions that need read,
 * write and registry access. Classes that require additional dependencies
 * (e.g. TestMappingCalculator, CouplingCalculator) override the constructor
 * and forward to the trait constructor via `__construct as __mrwTraitConstruct`.
 */
trait MetricsReaderWriterTrait
{
    public function __construct(
        protected readonly MetricsReaderInterface $reader,
        protected readonly MetricsWriterInterface $writer,
        protected readonly MetricsRegistryInterface $registry,
    ) {
    }
}
