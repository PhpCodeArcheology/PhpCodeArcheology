<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Application\Application;
use PhpCodeArch\Metrics\Manager\MetricsManager;
use PhpCodeArch\Metrics\Manager\MetricValue;
use PhpCodeArch\Metrics\Metrics;

trait ReportDataProviderTrait
{
    private array $templateData = [];
    public function __construct(
        private readonly Metrics $metrics,
        private readonly MetricsManager $metricsManager)
    {
        $this->templateData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->templateData['version'] = Application::VERSION;
        $this->gatherData();
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    private function setDataFromMetricTypesAndArrayToArrayKey(array $data, array $detailMetrics, string $arrayKey): array
    {
        return array_map(function($values) use ($detailMetrics, $arrayKey) {
            $detailData = [];

            foreach ($detailMetrics as $metricType) {
                if (! isset($values[$metricType->getKey()])) {
                    continue;
                }

//                $metricValue = MetricValue::ofValueAndType(
//                    $values[$metricType->getKey()],
//                    $metricType
//                );

                $metricValue = $values[$metricType->getKey()];

                if (! $metricValue instanceof MetricValue) {

                    continue;
                }

                $detailData[] = $metricValue;
//                $detailData[] = [
//                    'label' => $metricType->getName(),
//                    'shortName' => $metricType->getShortname(),
//                    'description' => $metricType->getDescription(),
//                    'value' => (string) $metricValue,
//                    'type' => gettype($metricValue->getValue()),
//                ];
            }

            $values[$arrayKey] = $detailData;

            return $values;
        }, $data);
    }
}
