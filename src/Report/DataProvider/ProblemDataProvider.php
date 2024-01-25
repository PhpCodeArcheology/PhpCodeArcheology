<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Model\MetricValue;

class ProblemDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $problemData = [
            'files' => 'fileProblems',
            'classes' => 'classProblems',
            'functions' => 'functionProblems',
        ];

        foreach ($problemData as $reportKey => $dataKey) {
            $elements = $this->reportDataContainer->get($reportKey)->getAll();
            $problems = $this->getProblemData($elements);
            $this->templateData[$dataKey] = $problems;
        }
    }

    private function getProblems(array $data): \Generator
    {
        foreach ($data as $id => $elementData) {
            foreach ($elementData as $metricValue) {
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
