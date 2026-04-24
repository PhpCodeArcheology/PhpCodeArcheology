<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\Service\BootstrapService;
use PhpCodeArch\Calculators\CalculatorRegistry;
use PhpCodeArch\Metrics\Controller\MetricsReader;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistry;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriter;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\PredictionRegistry;
use PhpCodeArch\Report\ReportOrchestrator;

final class ServiceFactory
{
    public function createBootstrapService(): BootstrapService
    {
        return new BootstrapService();
    }

    public function createAnalysisPipeline(): AnalysisPipeline
    {
        return new AnalysisPipeline($this);
    }

    public function createReportOrchestrator(): ReportOrchestrator
    {
        return new ReportOrchestrator();
    }

    public function createCalculatorRegistry(
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
    ): CalculatorRegistry {
        return new CalculatorRegistry($reader, $writer, $registry);
    }

    public function createPredictionRegistry(): PredictionRegistry
    {
        return new PredictionRegistry();
    }

    /**
     * Creates a Registry/Reader/Writer trio backed by a single shared
     * MetricsContainer. All three objects observe the same state — this is
     * the only supported way to obtain the trio.
     *
     * @return array{0: MetricsRegistry, 1: MetricsReader, 2: MetricsWriter, 3: MetricsContainer}
     */
    public function createMetricsTriple(): array
    {
        $container = new MetricsContainer();
        $registry = new MetricsRegistry($container);
        $reader = new MetricsReader($container);
        $writer = new MetricsWriter($container);

        return [$registry, $reader, $writer, $container];
    }
}
