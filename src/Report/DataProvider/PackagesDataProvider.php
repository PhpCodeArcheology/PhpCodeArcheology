<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

class PackagesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;


    public function gatherData(): void
    {
        $packages = $this->metrics->get('packages');

        $aiMap = [];

        $packages = array_map(function($packageName) use (&$aiMap) {
            $metric = $this->metrics->get($packageName);

            if (! $metric || count($metric->get('classes')) === 0) {
                return null;
            }

            $data = $metric->getAll();
            $data['name'] = $metric->getName();

            $a = $metric->get('abstractness');
            $i = $metric->get('instability');

            $aiKey = sprintf('%s-%s', $a, $i);

            if (! isset($aiMap[$aiKey])) {
                $aiMap[$aiKey] = [
                    'x' => $i,
                    'y' => $a,
                    'packages' => [],
                ];
            }

            $aiMap[$aiKey]['packages'][] = $packageName;

            return $data;
        }, $packages);

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
        $this->templateData['packages'] = array_filter($packages, fn($metric) => $metric !== null);
    }
}
