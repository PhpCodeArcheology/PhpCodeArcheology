<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class PackagesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $packages = $this->reportDataContainer->get('packages')->getAll();
        $packages = array_filter($packages, function($package) {
            $packageName = $package['name'];
            $metric = $this->metricsController->getMetricCollection(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName]
            );
            $classes = $metric->getCollection('classes');
            return count($classes->getAsArray()) !== 0;
        });

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::PackageCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::PackageCollection
        );

        $packages = $this->setDataFromMetricTypesAndArrayToArrayKey($packages, $detailMetrics, 'detailData');
        $packages = $this->setDataFromMetricTypesAndArrayToArrayKey($packages, $listMetrics, 'listData');

        $aiMap = [];
        foreach ($packages as $package) {
            $packageName = $package['name'];
            $metric = $this->metricsController->getMetricCollection(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName]
            );

            $a = $metric->get('abstractness')->getValue();
            $i = $metric->get('instability')->getValue();

            $aiKey = sprintf('%s-%s', $a, $i);

            if (! isset($aiMap[$aiKey])) {
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

        $this->templateData['aiChart'] = $chartData;

        $this->templateData['packages'] = $packages;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);
    }
}
