<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;

class ProjectDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    function gatherData(): void
    {
        $projectMetrics = $this->metrics->get('project');

        $this->calculateData($projectMetrics);

        $metrics = [
            'overallFiles' => 'Files',
            'overallFileErrors' => 'File errors',
            'overallFunctions' => 'Functions',
            'overallClasses' => 'Classes',
            'overallAbstractClasses' => 'Abstract classes',
            'overallInterfaces' => 'Interfaces',
            'overallMethods' => 'Methods',
            'overallPrivateMethods' => 'Private methods',
            'overallPublicMethods' => 'Public methods',
            'overallStaticMethods' => 'Static methods',
            'overallLoc' => 'Lines of code',
            'overallCloc' => 'Comment lines',
            'overallLloc' => 'Logical lines of code',
            'overallMaxCC' => 'Max. cyclomatic complexity',
            'overallMostComplexFile' => 'Most complex file',
            'overallMostComplexClass' => 'Most complex class',
            'overallMostComplexMethod' => 'Most complex method',
            'overallMostComplexFunction' => 'Most complex function',
            'overallAvgCC' => 'Average complexity',
            'overallAvgCCFile' => 'Average file complexity',
            'overallAvgCCClass' => 'Average class complexity',
            'overallAvgCCMethod' => 'Average method complexity',
            'overallAvgCCFunction' => 'Average function complexity',
        ];

        $data = [];
        foreach ($metrics as $key => $label) {
            $value = $projectMetrics->get($key);
            $value = is_numeric($value) ? number_format($value) : $value;

            $data[] = ['name' => $label, 'value' => $value];
        }

        $this->templateData['elements'] = $data;
    }

    private function calculateData(MetricsInterface $metrics): void
    {
        $data = [
            'overallMostComplexFile' => [],
            'overallMostComplexClass' => [],
            'overallMostComplexMethod' => [],
            'overallMostComplexFunction' => [],
            'overallMaxCC' => 0,
            'overallAvgCC' => 0,
            'overallAvgCCFile' => 0,
            'overallAvgCCClass' => 0,
            'overallAvgCCMethod' => 0,
            'overallAvgCCFunction' => 0,
        ];

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
            if (is_array($metric)) {
                continue;
            }

            $maxCC = $maxCC < $metric->get('cc') ? $metric->get('cc') : $maxCC;
            $sumCC += $metric->get('cc');

            switch (true) {
                case $metric instanceof FileMetrics:
                    $data['overallMostComplexFile'][$metric->getName()] = $metric->get('cc');
                    $maxCCFile = $maxCCFile < $metric->get('cc') ? $metric->get('cc') : $maxCCFile;
                    $sumCCFile += $metric->get('cc');
                    break;

                case $metric instanceof FunctionMetrics:
                    $data['overallMostComplexFunction'][$metric->getName()] = $metric->get('cc');
                    $maxCCFunction = $maxCCFunction < $metric->get('cc') ? $metric->get('cc') : $maxCCFunction;
                    $sumCCFunction += $metric->get('cc');
                    break;

                case $metric instanceof ClassMetrics:
                    $data['overallMostComplexClass'][$metric->getName()] = $metric->get('cc');
                    $maxCCClass = $maxCCClass < $metric->get('cc') ? $metric->get('cc') : $maxCCClass;
                    $sumCCClass += $metric->get('cc');

                    foreach ($metric->get('methods') as $methodMetric) {
                        $data['overallMostComplexMethod'][$metric->getName() . '::' . $methodMetric->getName()] = $methodMetric->get('cc');
                        $maxCCMethod = $maxCCMethod < $metric->get('cc') ? $metric->get('cc') : $maxCCMethod;
                        $sumCCMethod += $metric->get('cc');
                    }
                    break;
            }
        }

        foreach ($data as $key => $ccValues) {
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

            $data[$key] = $output;
        }

        $fileCount = $metrics->get('overallFiles');
        $classCount = $metrics->get('overallClasses');
        $functionCount = $metrics->get('overallFunctions');
        $methodCount = $metrics->get('overallMethods');

        $data['overallMaxCC'] = $maxCC;
        $data['overallMaxCCFile'] = $maxCCFile;
        $data['overallMaxCCClass'] = $maxCCClass;
        $data['overallMaxCCMethod'] = $maxCCMethod;
        $data['overallMaxCCFunction'] = $maxCCFunction;
        $data['overallAvgCC'] = $this->getAvgOrZero($sumCC, count($this->metrics->getAll()));
        $data['overallAvgCCFile'] = $this->getAvgOrZero($sumCCFile, $fileCount);
        $data['overallAvgCCClass'] = $this->getAvgOrZero($sumCCClass, $classCount);
        $data['overallAvgCCMethod'] = $this->getAvgOrZero($sumCCMethod, $methodCount);
        $data['overallAvgCCFunction'] = $this->getAvgOrZero($sumCCFunction, $functionCount);

        foreach ($data as $key => $value) {
            $metrics->set($key, $value);
        }

        $this->metrics->set('project', $metrics);
    }

    private function getAvgOrZero(int $value, int $count): int|float
    {
        if ($count === 0) {
            return 0;
        }

        return $value / $count;
    }
}
