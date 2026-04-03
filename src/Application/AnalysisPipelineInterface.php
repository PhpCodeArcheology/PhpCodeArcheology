<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Metrics\Controller\MetricsController;

interface AnalysisPipelineInterface
{
    /**
     * @return array{MetricsController, array<int, int>}
     */
    public function runAnalysis(Config $config, CliOutput $output): array;
}
