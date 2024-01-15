<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Data;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Report\DataProvider\ChartDataProvider;
use PhpCodeArch\Report\DataProvider\ClassDataProvider;
use PhpCodeArch\Report\DataProvider\FilesDataProvider;
use PhpCodeArch\Report\DataProvider\PackagesDataProvider;
use PhpCodeArch\Report\DataProvider\ProjectDataProvider;

class DataProviderFactory
{
    private array $data = [];

    public function __construct(
        private readonly MetricsContainer  $metrics,
        private readonly MetricsController $metricsManager)
    {
    }

    public function getProjectData(): ProjectDataProvider
    {
        return new ProjectDataProvider($this->metrics, $this->metricsManager);
    }

    public function getFiles(): FilesDataProvider
    {
        return new FilesDataProvider($this->metrics, $this->metricsManager);
    }

    public function getClassAIChartData(): ChartDataProvider
    {
        return new ChartDataProvider($this->metrics, $this->metricsManager);
    }

    public function getClasses(): ClassDataProvider
    {
        return new ClassDataProvider($this->metrics, $this->metricsManager);
    }

    public function getPackages(): PackagesDataProvider
    {
        return new PackagesDataProvider($this->metrics, $this->metricsManager);
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
