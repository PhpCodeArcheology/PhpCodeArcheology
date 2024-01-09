<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Metric;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $files;

    public function gatherData(): void
    {
        $files = $this->metrics->get('project')->get('files');

        $detailMetrics = [
            Metric::ofKeyLabelAndType('loc', 'Lines of code', 'int'),
            Metric::ofKeyLabelAndType('lloc', 'Logical lines of code', 'int'),
            Metric::ofKeyLabelAndType('cloc', 'Lines of comments', 'int'),
            Metric::ofKeyLabelAndType('llocOutside', 'Lines outside fn/cl', 'int'),
            Metric::ofKeyLabelAndType('htmlLoc', 'Lines of HTML', 'int'),
            Metric::ofKeyLabelAndType('htmlPercentage', 'HTML in percent', 'percent'),
            Metric::ofKeyLabelAndType('superglobals', 'Superglobals', 'count'),
            Metric::ofKeyLabelAndType('variables', 'Variables', 'count'),
            Metric::ofKeyLabelAndType('constants', 'Constants', 'count'),
            Metric::ofKeyLabelAndType('cc', 'Cyclomatic Complexity', 'int'),
            Metric::ofKeyLabelAndType('complexityDensity', 'Complexity density', 'float'),
            Metric::ofKeyLabelAndType('vocabulary', 'Vocabulary', 'int'),
            Metric::ofKeyLabelAndType('length', 'Length', 'int'),
            Metric::ofKeyLabelAndType('volume', 'Volume', 'float'),
            Metric::ofKeyLabelAndType('difficulty', 'Difficulty', 'float'),
            Metric::ofKeyLabelAndType('effort', 'Effort', 'float'),
            Metric::ofKeyLabelAndType('maintainabilityIndex', 'Maintainability index', 'float'),
            Metric::ofKeyLabelAndType('commentWeight', 'Comment weight', 'float'),
            Metric::ofKeyLabelAndType('functions', 'Functions', 'count'),
            Metric::ofKeyLabelAndType('classes', 'Classes', 'count'),
        ];

        $files = array_map(function($file) use ($detailMetrics) {
            $detailData = [];

            foreach ($detailMetrics as $metric) {
                if (! isset($file[$metric->getKey()])) {
                    continue;
                }

                $value = $file[$metric->getKey()];

                switch ($metric->getType()) {
                    case 'array':
                        $value = implode(', ', $value);
                        break;

                    case 'count':
                        $value = count($value);
                        break;

                    case 'float':
                        $value = number_format($value, 2);
                        break;

                    case 'percent':
                        $value = number_format($value, 2) . '%';
                        break;

                }

                $detailData[] = [
                    'label' => $metric->getLabel(),
                    'value' => $value,
                    'type' => $metric->getType(),
                ];
            }

            $file['detailData'] = $detailData;

            return $file;
        }, $files);

        $this->templateData['files'] = $files;
        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
