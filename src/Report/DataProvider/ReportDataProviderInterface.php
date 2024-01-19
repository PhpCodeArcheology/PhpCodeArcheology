<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Report\Data\ReportDataContainer;

interface ReportDataProviderInterface
{
    public function __construct(MetricsController $metricsController, ReportDataContainer $reportDataContainer);

    public function getTemplateData(): array;

    public function gatherData(): void;
}
