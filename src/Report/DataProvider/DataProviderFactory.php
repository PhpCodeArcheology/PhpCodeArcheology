<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Report\Data\ReportDataContainer;

class DataProviderFactory
{
    private array $data = [];

    public function __construct(
        private readonly MetricsController $metricsController,
        private ReportDataContainer $reportDataContainer)
    {
    }

    public function getProjectDataProvider(): ProjectDataProvider
    {
        return new ProjectDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getFilesDataProvider(): FilesDataProvider
    {
        return new FilesDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getClassDataProvider(): ClassDataProvider
    {
        return new ClassDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getPackagDataProvider(): PackagesDataProvider
    {
        return new PackagesDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getClassCouplingDataProvider(): ClassCouplingDataProvider
    {
        return new ClassCouplingDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getClassesChartDataProvider(): ClassesChartDataProvider
    {
        return new ClassesChartDataProvider($this->metricsController, $this->reportDataContainer);
    }

    public function getFunctionDataProvider(): FunctionDataProvider
    {
        return new FunctionDataProvider($this->metricsController, $this->reportDataContainer);
    }

    private function predictProgrammingParadigm(): void
    {
        $classCount = $this->data['OverallClasses'];
        $functionCount = $this->data['OverallFunctions'];
        $methodCount = $this->data['OverallMethods'];

        $lloc = $this->data['OverallLloc'];
        $llocOutside = $this->data['OverallLlocOutside'];
        $OverallInsideMethodLloc = $this->data['OverallInsideMethodLloc'];
        $OverallInsideFuntionLloc = $this->data['OverallInsideFuntionLloc'];

        $maxCC = $this->data['OverallMaxCC'];
        $maxCCFile = $this->data['OverallMaxCCFile'];
        $maxCCClass = $this->data['OverallMaxCCClass'];
        $maxCCMethod = $this->data['OverallMaxCCMethod'];
        $maxCCFunction = $this->data['OverallMaxCCFunction'];

        $avgCC = $this->data['OverallAvgCC'];
        $avgCCFile = $this->data['OverallAvgCCFile'];
        $avgCCClass = $this->data['OverallAvgCCClass'];
        $avgCCMethod = $this->data['OverallAvgCCMethod'];
        $avgCCFunction = $this->data['OverallAvgCCFunction'];

        $methodsToFunctionsScore = $methodCount / ($functionCount + $methodCount);
        $llocToLlocOutsideScore = $llocOutside / $lloc;
        $methodsToFunctionsLlocScore = $OverallInsideMethodLloc / ($OverallInsideFuntionLloc + $OverallInsideMethodLloc);
        $cyclomaticComplexityScore = 1 / $avgCC;

        $weights = [
            'methodsToFunctions' => 0.2,
            'llocToLlocOutside' => 0.5,
            'methodsToFunctionsLloc' => 0.2,
            'cyclomaticComplexity' => 0.1
        ];

        $totalScore = ($methodsToFunctionsScore * $weights['methodsToFunctions']) +
            ($llocToLlocOutsideScore * $weights['llocToLlocOutside']) +
            ($methodsToFunctionsLlocScore * $weights['methodsToFunctionsLloc']) +
            ($cyclomaticComplexityScore * $weights['cyclomaticComplexity']);
    }
}
