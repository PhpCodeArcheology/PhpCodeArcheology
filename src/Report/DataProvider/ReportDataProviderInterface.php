<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Metrics;

interface ReportDataProviderInterface
{
    public function __construct(Metrics $metric);

    public function getTemplateData(): array;

    public function gatherData(): void;
}
