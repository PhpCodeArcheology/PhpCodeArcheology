<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use phpDocumentor\Reflection\File;

class ProblemDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $problemData = [
            'files' => [
                'key' => 'fileProblems',
                'metrics' => [],
            ],
            'classes' => [
                'key' => 'classProblems',
                'metrics' => [],
            ],
            'functions' => [
                'key' => 'functionProblems',
                'metrics' => [],
            ],
        ];

        foreach ($this->repository->getAllMetricCollections() as $metrics) {
            switch (true) {
                case $metrics instanceof FileMetricsCollection:
                    $problemData['files']['metrics'][(string) $metrics->getIdentifier()] = $metrics;
                    break;

                case $metrics instanceof FunctionMetricsCollection:
                    $problemData['functions']['metrics'][(string) $metrics->getIdentifier()] = $metrics;
                    break;

                case $metrics instanceof ClassMetricsCollection:
                    $problemData['classes']['metrics'][(string) $metrics->getIdentifier()] = $metrics;
                    break;
            }
        }

        foreach ($problemData as $data) {
            $problems = $this->getProblemData($data['metrics']);
            $this->templateData[$data['key']] = $problems;
        }
    }

    private function getProblems(array $data): \Generator
    {
        foreach ($data as $id => $elementData) {
            foreach ($elementData->getAll() as $metricValue) {
                if (! $metricValue instanceof MetricValue || ! $metricValue->hasProblems()) {
                    continue;
                }

                yield [
                    'id' => $id,
                    'problems' => $metricValue->getProblems(),
                    'data' => $elementData,
                ];
            }
        }
    }

    private function getProblemData(array $elements): array
    {
        $problemDataContainer = [];

        foreach ($this->getProblems($elements) as $problemData) {
            if (!isset($problemDataContainer[$problemData['id']])) {
                $problemDataContainer[$problemData['id']] = $problemData;

                continue;
            }

            $problemDataContainer[$problemData['id']]['problems'] = array_merge(
                $problemDataContainer[$problemData['id']]['problems'],
                $problemData['problems']
            );
        }

        return $problemDataContainer;
    }
}
