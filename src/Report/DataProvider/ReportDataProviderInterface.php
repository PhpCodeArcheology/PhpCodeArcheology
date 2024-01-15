<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;

interface ReportDataProviderInterface
{
    public function __construct(MetricsContainer $metric, MetricsController $metricsManager);

    public function getTemplateData(): array;

    public function gatherData(): void;
}
