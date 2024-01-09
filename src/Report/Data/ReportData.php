<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report\Data;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Report\DataProvider\ChartDataProvider;
use Marcus\PhpLegacyAnalyzer\Report\DataProvider\ClassDataProvider;
use Marcus\PhpLegacyAnalyzer\Report\DataProvider\FilesDataProvider;
use Marcus\PhpLegacyAnalyzer\Report\DataProvider\ProjectDataProvider;

class ReportData
{
    private array $data = [];

    public function __construct(private readonly Metrics $metrics)
    {
    }

    public function getProjectData(): ProjectDataProvider
    {
        return new ProjectDataProvider($this->metrics);
    }

    public function getFiles(): FilesDataProvider
    {
        return new FilesDataProvider($this->metrics);
    }

    public function getClassAIChartData(): ChartDataProvider
    {
        return new ChartDataProvider($this->metrics);
    }

    public function getClasses(): ClassDataProvider
    {
        return new ClassDataProvider($this->metrics);
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
