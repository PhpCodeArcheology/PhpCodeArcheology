<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;

interface AnalysisPipelineInterface
{
    /**
     * @return array{MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface, array<int, int>}
     */
    public function runAnalysis(Config $config, CliOutput $output): array;
}
