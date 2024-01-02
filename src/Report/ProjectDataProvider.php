<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use Marcus\PhpLegacyAnalyzer\Metrics\OverallMetricsEnum;

class ProjectDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    function gatherData(): void
    {
        $projectMetrics = $this->metrics->get('project');

        $metrics = OverallMetricsEnum::asArray();

        $data = [];
        foreach ($metrics as $key => $label) {
            $value = $projectMetrics->get($key);
            $value = $value ?? '-';
            $value = is_numeric($value) ? number_format($value, is_float($value) ? 2 : 0) : $value;

            $data[] = ['name' => $label, 'value' => $value];
        }

        $this->templateData['elements'] = $data;
    }
}
