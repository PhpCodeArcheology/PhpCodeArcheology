<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

interface ReportDataProviderInterface
{
    public function __construct(Metrics $metric);

    public function getTemplateData(): array;

    public function gatherData(): void;
}
