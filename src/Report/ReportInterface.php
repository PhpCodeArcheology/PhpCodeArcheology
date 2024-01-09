<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\CliOutput;
use Marcus\PhpLegacyAnalyzer\Application\Config;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

interface ReportInterface
{
    public function __construct(
        Config $config,
        ReportData $reportData,
        FilesystemLoader $twigLoader,
        Environment $twig,
        CliOutput $output);

    public function generate(): void;
}
