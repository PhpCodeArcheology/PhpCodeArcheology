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
        $classCount = $this->data['overallClasses'];
        $functionCount = $this->data['overallFunctions'];
        $methodCount = $this->data['overallMethods'];

        $lloc = $this->data['overallLloc'];
        $llocOutside = $this->data['overallLlocOutside'];
        $overallInsideMethodLloc = $this->data['overallInsideMethodLloc'];
        $overallInsideFuntionLloc = $this->data['overallInsideFuntionLloc'];

        $maxCC = $this->data['overallMaxCC'];
        $maxCCFile = $this->data['overallMaxCCFile'];
        $maxCCClass = $this->data['overallMaxCCClass'];
        $maxCCMethod = $this->data['overallMaxCCMethod'];
        $maxCCFunction = $this->data['overallMaxCCFunction'];

        $avgCC = $this->data['overallAvgCC'];
        $avgCCFile = $this->data['overallAvgCCFile'];
        $avgCCClass = $this->data['overallAvgCCClass'];
        $avgCCMethod = $this->data['overallAvgCCMethod'];
        $avgCCFunction = $this->data['overallAvgCCFunction'];

        $methodsToFunctionsScore = $methodCount / ($functionCount + $methodCount);
        $llocToLlocOutsideScore = $llocOutside / $lloc;
        $methodsToFunctionsLlocScore = $overallInsideMethodLloc / ($overallInsideFuntionLloc + $overallInsideMethodLloc);
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
