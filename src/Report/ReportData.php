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

    private ProjectMetrics $projectMetrics;

    private array $overallMetrics = [];

    public function __construct(private Metrics $metrics)
    {
        $this->projectMetrics = $this->metrics->get('project');
        $this->overallMetrics = $this->projectMetrics->getOverallMetrics();
    }

    public function generate(): void
    {
        $this->transferOverallData();
        $this->createClassArray();
        $this->calculateMaxComplexities();
        $this->calculateCoupling();
        $this->getQualityMetrics();
        $this->getVariableMetrics();

        $this->predictProgrammingParadigm();
    }

    public function getOverallData(): array
    {
        return array_intersect_key($this->data, $this->overallMetrics);
    }

    public function getClasses(): array
    {
        return $this->data['classes'];
    }

    private function transferOverallData(): void
    {
        foreach ($this->overallMetrics as $key => $value) {
            $this->data[$key] = $this->projectMetrics->get($key);
        }
    }

    private function calculateMaxComplexities(): void
    {

        $cc = [
            'overallMostComplexFile' => [],
            'overallMostComplexClass' => [],
            'overallMostComplexMethod' => [],
            'overallMostComplexFunction' => [],
        ];

        $this->overallMetrics['overallMaxCC'] = 0;
        $this->overallMetrics['overallAvgCC'] = 0;
        $this->overallMetrics['overallAvgCCFile'] = 0;
        $this->overallMetrics['overallAvgCCClass'] = 0;
        $this->overallMetrics['overallAvgCCMethod'] = 0;
        $this->overallMetrics['overallAvgCCFunction'] = 0;

        $maxCC = 0;
        $maxCCFile = 0;
        $maxCCClass = 0;
        $maxCCMethod = 0;
        $maxCCFunction = 0;

        $sumCC = 0;
        $sumCCFile = 0;
        $sumCCClass = 0;
        $sumCCMethod = 0;
        $sumCCFunction = 0;

        foreach ($this->metrics->getAll() as $metric) {
            $maxCC = $maxCC < $metric->get('cc') ? $metric->get('cc') : $maxCC;
            $sumCC += $metric->get('cc');

            switch (true) {
                case $metric instanceof FileMetrics:
                    $cc['overallMostComplexFile'][$metric->getName()] = $metric->get('cc');
                    $maxCCFile = $maxCCFile < $metric->get('cc') ? $metric->get('cc') : $maxCCFile;
                    $sumCCFile += $metric->get('cc');
                    break;

                case $metric instanceof FunctionMetrics:
                    $cc['overallMostComplexFunction'][$metric->getName()] = $metric->get('cc');
                    $maxCCFunction = $maxCCFunction < $metric->get('cc') ? $metric->get('cc') : $maxCCFunction;
                    $sumCCFunction += $metric->get('cc');
                    break;

                case $metric instanceof ClassMetrics:
                    $cc['overallMostComplexClass'][$metric->getName()] = $metric->get('cc');
                    $maxCCClass = $maxCCClass < $metric->get('cc') ? $metric->get('cc') : $maxCCClass;
                    $sumCCClass += $metric->get('cc');

                    foreach ($metric->get('methods') as $methodMetric) {
                        $cc['overallMostComplexMethod'][$metric->getName() . '::' . $methodMetric->getName()] = $methodMetric->get('cc');
                        $maxCCMethod = $maxCCMethod < $metric->get('cc') ? $metric->get('cc') : $maxCCMethod;
                        $sumCCMethod += $metric->get('cc');
                    }
                    break;
            }
        }

        foreach ($cc as $key => $ccValues) {
            if (empty($ccValues)) {
                continue;
            }

            $maxValue = max($ccValues);
            $keysWithMaxValue = array_keys($ccValues, $maxValue);
            $output = sprintf(
                '%s: %d',
                implode(', ', $keysWithMaxValue),
                $maxValue
            );

            $this->data[$key] = $output;
        }

        $this->data['overallMaxCC'] = $maxCC;
        $this->data['overallMaxCCFile'] = $maxCCFile;
        $this->data['overallMaxCCClass'] = $maxCCClass;
        $this->data['overallMaxCCMethod'] = $maxCCMethod;
        $this->data['overallMaxCCFunction'] = $maxCCFunction;

        $fileCount = $this->data['overallFiles'];
        $classCount = $this->data['overallClasses'];
        $functionCount = $this->data['overallFunctions'];
        $methodCount = $this->data['overallMethods'];

        $this->data['overallAvgCC'] = $this->getAvgOrZero($sumCC, count($this->metrics->getAll()));
        $this->data['overallAvgCCFile'] = $this->getAvgOrZero($sumCCFile, $fileCount);
        $this->data['overallAvgCCClass'] = $this->getAvgOrZero($sumCCClass, $classCount);
        $this->data['overallAvgCCMethod'] = $this->getAvgOrZero($sumCCMethod, $methodCount);
        $this->data['overallAvgCCFunction'] = $this->getAvgOrZero($sumCCFunction, $functionCount);
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

    private function getAvgOrZero(int $value, int $count): int|float
    {
        if ($count === 0) {
            return 0;
        }

        return $value / $count;
    }

    private function calculateCoupling(): void
    {
        $classes = $this->data['classes'];

        // Get all dependencies
        foreach ($this->metrics->getAll() as $metric) {
            if ($metric instanceof FileMetrics) {
                if (! is_array($metric->get('dependencies'))) {
                    continue;
                }

                foreach ($metric->get('dependencies') as $dependency) {
                    if (! isset($classes[$dependency])) {
                        $classes[$dependency] = [
                            'id' => null,
                            'internal' => false,
                            'uses' => [],
                            'usedBy' => [],
                            'usedByFunction' => [],
                            'usedFromOutside' => [],
                            'usesCount' => 0,
                            'usedByCount' => 0,
                            'usedByFunctionCount' => 0,
                            'usedFromOutsideCount' => 0,
                        ];
                    }

                    $classes[$dependency]['usedFromOutside'][] = $metric->getName();
                    ++ $classes[$dependency]['usedFromOutsideCount'];
                }

                continue;
            }

            if ($metric instanceof FunctionMetrics) {
                foreach ($metric->get('dependencies') as $dependency) {
                    if (! isset($classes[$dependency])) {
                        $classes[$dependency] = [
                            'id' => null,
                            'internal' => false,
                            'uses' => [],
                            'usedBy' => [],
                            'usedByFunction' => [],
                            'usedFromOutside' => [],
                            'usesCount' => 0,
                            'usedByCount' => 0,
                            'usedByFunctionCount' => 0,
                            'usedFromOutsideCount' => 0,
                        ];
                    }

                    $classes[$dependency]['usedByFunction'][] = $metric->getName();
                    ++ $classes[$dependency]['usedByFunctionCount'];
                }

                continue;
            }

            if (! $metric instanceof ClassMetrics) {
                continue;
            }

            foreach ($metric->get('dependencies') as $dependency) {
                if ($metric->getName() === $dependency) {
                    continue;
                }

                $classes[$metric->getName()]['uses'][] = $dependency;
                ++ $classes[$metric->getName()]['usesCount'];

                if (! isset($classes[$dependency])) {
                    $classes[$dependency] = [
                        'id' => null,
                        'internal' => false,
                        'uses' => [],
                        'usedBy' => [],
                        'usedByFunction' => [],
                        'usedFromOutside' => [],
                        'usesCount' => 0,
                        'usedByCount' => 0,
                        'usedByFunctionCount' => 0,
                        'usedFromOutsideCount' => 0,
                    ];
                }

                $classes[$dependency]['usedBy'][] = $metric->getName();
                ++ $classes[$dependency]['usedByCount'];
            }
        }

        // Save metrics and add to report data
        foreach ($classes as $className => $class) {
            $this->data['classes'][$className] = $class;

            if ($class['id'] === null) {
                continue;
            }

            $metric = $this->metrics->get($class['id']);

            foreach ($class as $key => $value) {
                if ($key === 'id') {
                    continue;
                }

                $metric->set($key, $value);
            }
        }
    }

    private function createClassArray(): void
    {
        $classes = [];

        foreach ($this->metrics->getAll() as $metric) {
            if (! $metric instanceof ClassMetrics) {
                continue;
            }

            $classes[$metric->getName()] = [
                'id' => (string) $metric->getIdentifier(),
                'internal' => true,
                'uses' => [],
                'usedBy' => [],
                'usedByFunction' => [],
                'usedFromOutside' => [],
                'usesCount' => 0,
                'usedByCount' => 0,
                'usedByFunctionCount' => 0,
                'usedFromOutsideCount' => 0,
            ];
        }

        $this->data['classes'] = $classes;
    }

    private function getQualityMetrics(): void
    {
        $qualityMetrics = [
            'vocabulary',
            'length',
            'calcLength',
            'volume',
            'difficulty',
            'effort',
            'complexityDensity',
            'maintainabilityIndex',
            'maintainabilityIndexWithoutComments',
            'commentWeight',
            'lcom',
        ];

        foreach ($this->data['classes'] as $className => $class) {
            if (! $class['id']) {
                continue;
            }

            $classMetrics = $this->metrics->get($class['id']);

            foreach ($qualityMetrics as $key) {
                $class[$key] = $classMetrics->get($key);
            }

            $this->data['classes'][$className] = $class;
        }
    }

    private function getVariableMetrics()
    {
        foreach ($this->data['classes'] as $className => $class) {
            if (!$class['id']) {
                continue;
            }

            $classMetrics = $this->metrics->get($class['id']);
            $superglobals = $classMetrics->get('superglobals');
            $variables = $classMetrics->get('variables');

            $class['superglobalsUsed'] = array_sum($superglobals);
            $class['distinctSuperglobalsUsed'] = count(array_filter($superglobals, fn($variableCount) => $variableCount > 0));
            $class['variablesUsed'] = array_sum($variables);
            $class['distinctVariablesUsed'] = count($variables);
            $class['superglobalMetric'] = $class['variablesUsed'] > 0 ?
                round(($class['superglobalsUsed'] / ($class['superglobalsUsed'] + $class['variablesUsed'])) * 100, 2)
                : 0;

            $this->data['classes'][$className] = $class;
        }
    }
}
