<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Application\Application;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\Data\ReportDataContainer;

trait ReportDataProviderTrait
{
    private array $templateData = [];
    public function __construct(
        private readonly MetricsController $metricsController,
        private readonly ReportDataContainer $reportDataContainer)
    {
        $this->templateData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->templateData['version'] = Application::VERSION;
        $this->gatherData();
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    private function setDataFromMetricTypesAndArrayToArrayKey(array $data, array $metricTypes, string $arrayKey): array
    {
        return array_map(function($values) use ($metricTypes, $arrayKey) {
            $detailData = [];

            foreach ($metricTypes as $metricType) {
                if (! isset($values[$metricType->getKey()])) {
                    $valueData = $metricType->__toArray();
                    $valueData['value'] = '-'.$metricType->getKey(); // TODO Remove this
                    $valueData['rawValue'] = '-';
                    $valueData['sortValue'] = -1;
                    $detailData[] = $valueData;

                    continue;
                }

                $metricValue = $values[$metricType->getKey()];

                if (! $metricValue instanceof MetricValue) {
                    continue;
                }

                $valueData = $metricType->__toArray();
                $valueData['value'] = $metricValue->__toString();
                $valueData['rawValue'] = $metricValue->getValue();
                $valueData['sortValue'] = $metricValue->getSortValue();
                $valueData['problemLevel'] = $metricValue->getMaxProblemLevel();
                $valueData['problemText'] = $metricValue->getProblemMessages();

                $detailData[] = $valueData;
            }

            $values[$arrayKey] = $detailData;

            return $values;
        }, $data);
    }
}
