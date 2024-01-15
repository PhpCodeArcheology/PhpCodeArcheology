<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Model\ProjectMetrics\OverallMetricsEnum;

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

            $data[] = ['label' => $label, 'value' => $value];
        }

        $this->templateData['elements'] = $data;
    }
}
