<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;

class ReportData
{
    private array $data = [];

    private ProjectMetrics $projectMetrics;

    public function __construct(private Metrics $metrics)
    {
        $this->projectMetrics = $this->metrics->get('project');
    }

    public function generate(): void
    {
        $this->transferOverallData();
        $this->calculateMaxComplexities();
    }

    public function getOverallData(): array
    {
        $overallMetrics = $this->projectMetrics->getOverallMetrics();

        return array_intersect_key($this->data, $overallMetrics);
    }

    private function transferOverallData(): void
    {
        $overallMetrics = $this->projectMetrics->getOverallMetrics();

        foreach ($overallMetrics as $key => $value) {
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

        foreach ($this->metrics->getAll() as $metric) {
            switch (true) {
                case $metric instanceof FileMetrics:
                    $cc['overallMostComplexFile'][$metric->getName()] = $metric->get('cc');
                    break;

                case $metric instanceof FunctionMetrics:
                    $cc['overallMostComplexFunction'][$metric->getName()] = $metric->get('cc');
                    break;

                case $metric instanceof ClassMetrics:
                    $cc['overallMostComplexClass'][$metric->getName()] = $metric->get('cc');

                    foreach ($metric->get('methods') as $methodMetric) {
                        $cc['overallMostComplexMethod'][$metric->getName() . '::' . $methodMetric->getName()] = $methodMetric->get('cc');
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
    }
}