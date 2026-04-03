<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;

interface ReportDataProviderInterface
{
    public function __construct(MetricsReaderInterface $metricsController);

    /** @return array<string, mixed> */
    public function getTemplateData(): array;

    public function gatherData(): void;
}
