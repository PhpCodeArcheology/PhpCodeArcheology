<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
use phpDocumentor\Reflection\File;

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
