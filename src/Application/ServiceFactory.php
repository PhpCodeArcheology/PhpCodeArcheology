<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\Service\BootstrapService;
use PhpCodeArch\Calculators\CalculatorRegistry;
use PhpCodeArch\Metrics\Controller\MetricsController;
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

    public function createCalculatorRegistry(MetricsController $mc): CalculatorRegistry
    {
        return new CalculatorRegistry($mc);
    }

    public function createPredictionRegistry(): PredictionRegistry
    {
        return new PredictionRegistry();
    }

    public function createMetricsController(): MetricsController
    {
        $container = new MetricsContainer();

        return new MetricsController($container);
    }
}
