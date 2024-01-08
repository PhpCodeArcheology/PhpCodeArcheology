<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class ChartDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $classes = $this->metrics->get('classes');

        $aiMap = [];

        foreach ($classes as $classId => $className) {
            $classMetrics = $this->metrics->get($classId);

            $a = $classMetrics->get('abstractness');
            $i = $classMetrics->get('instability');

            $aiKey = sprintf('%s-%s', $a, $i);

            if (isset($aiMap[$aiKey])) {
                ++ $aiMap[$aiKey]['count'];
                continue;
            }

            $aiMap[$aiKey] = [
                'x' => $i,
                'y' => $a,
                'count' => 1,
            ];
        }

        $chartData = [
            'x' => [],
            'y' => [],
            'count' => [],
        ];

        foreach ($aiMap as $data) {
            $chartData['x'][] = $data['x'];
            $chartData['y'][] = $data['y'];
            $chartData['count'][] = $data['count'] . ' Class' . ($data['count'] > 1 ? 'es' : '');
        }

        $chartData['x'] = json_encode($chartData['x']);
        $chartData['y'] = json_encode($chartData['y']);
        $chartData['count'] = json_encode($chartData['count']);

        $this->templateData['aiChart'] = $chartData;
    }
}
