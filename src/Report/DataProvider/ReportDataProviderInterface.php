<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;

interface ReportDataProviderInterface
{
    public function __construct(MetricsReaderInterface $reader, MetricsRegistryInterface $registry);

    /** @return array<string, mixed> */
    public function getTemplateData(): array;

    public function gatherData(): void;
}
