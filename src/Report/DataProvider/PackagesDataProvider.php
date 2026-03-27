<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;

class PackagesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $packages = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'packages'
        );

        $packages = array_filter($packages, function ($package): bool {
            $classes = $package->getCollection('classes');

            return null !== $classes && 0 !== count($classes->getAsArray());
        });

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::PackageCollection
        );

        $aiMap = [];
        foreach ($packages as $package) {
            $packageName = $package->getName();

            $a = $package->getFloat(MetricKey::ABSTRACTNESS);
            $i = $package->getFloat(MetricKey::INSTABILITY);

            $aiKey = sprintf('%s-%s', $a, $i);

            if (!isset($aiMap[$aiKey])) {
                $aiMap[$aiKey] = [
                    'x' => $i,
                    'y' => $a,
                    'packages' => [],
                ];
            }

            $aiMap[$aiKey]['packages'][] = $packageName;
        }

        $chartData = [
            'x' => [],
            'y' => [],
            'count' => [],
        ];

        foreach ($aiMap as $data) {
            $chartData['x'][] = $data['x'];
            $chartData['y'][] = $data['y'];
            $chartData['count'][] = implode(', ', $data['packages']);
        }

        $chartData['x'] = json_encode($chartData['x']);
        $chartData['y'] = json_encode($chartData['y']);
        $chartData['count'] = json_encode($chartData['count']);

        $templateData = [
            'aiChart' => $chartData,
            'packages' => $packages,
            'tableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $listMetrics),
            'listMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $listMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
