<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

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

        foreach ($this->registry->getAllCollections() as $metrics) {
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

    /**
     * @param array<string, mixed> $data
     *
     * @return \Generator<int, array{id: string, problems: mixed[], data: mixed}>
     */
    private function getProblems(array $data): \Generator
    {
        foreach ($data as $id => $elementData) {
            if (!$elementData instanceof \PhpCodeArch\Metrics\Model\MetricsCollectionInterface) {
                continue;
            }
            foreach ($elementData->getAll() as $metricValue) {
                if (!$metricValue->hasProblems()) {
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

    /**
     * @param array<string, mixed> $elements
     *
     * @return array<string, array{id: string, problems: mixed[], data: mixed}>
     */
    private function getProblemData(array $elements): array
    {
        $problemDataContainer = [];

        foreach ($this->getProblems($elements) as $problemData) {
            $id = $problemData['id'];
            if (!isset($problemDataContainer[$id])) {
                $problemDataContainer[$id] = $problemData;

                continue;
            }

            $problemDataContainer[$id]['problems'] = array_merge(
                $problemDataContainer[$id]['problems'],
                $problemData['problems']
            );
        }

        return $problemDataContainer;
    }
}
