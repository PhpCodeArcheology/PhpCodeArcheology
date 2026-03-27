<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsController;

interface ReportDataProviderInterface
{
    public function __construct(MetricsController $metricsController);

    /** @return array<string, mixed> */
    public function getTemplateData(): array;

    public function gatherData(): void;
}
