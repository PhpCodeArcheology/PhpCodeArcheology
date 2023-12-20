<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\Config;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

interface ReportInterface
{
    public function __construct(Config $config, Metrics $metrics);

    public function generate(): void;
}