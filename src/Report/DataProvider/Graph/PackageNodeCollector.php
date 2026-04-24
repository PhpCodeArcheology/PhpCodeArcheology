<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider\Graph;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;

class PackageNodeCollector
{
    public function __construct(
        private readonly MetricsReaderInterface $reader,
    ) {
    }

    /**
     * @param array<string, string> $nameToId
     *
     * @return array{nodes: list<array<string, mixed>>, clusters: list<array<string, mixed>>}
     */
    public function collect(array $nameToId): array
    {
        $nodes = [];
        $clusters = [];

        $packages = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'packages'
        );

        foreach ($packages as $packageCollection) {
            $packageName = $packageCollection->getName();
            $packageNodeId = 'package:'.$packageName;

            $nodes[] = [
                'id' => $packageNodeId,
                'type' => 'package',
                'name' => $packageName,
                'metrics' => [
                    'abstractness' => $packageCollection->get(MetricKey::ABSTRACTNESS)?->getValue(),
                    'instability' => $packageCollection->get(MetricKey::INSTABILITY)?->getValue(),
                    'distanceFromMainline' => $packageCollection->get(MetricKey::DISTANCE_FROM_MAINLINE)?->getValue(),
                    'cohesion' => null,
                ],
                'problems' => [],
            ];

            $clusterNodeIds = [];
            $classesInPackage = $packageCollection->getCollection('classes');
            if (null !== $classesInPackage) {
                foreach ($classesInPackage->getAsArray() as $className) {
                    if (!is_string($className)) {
                        continue;
                    }
                    $resolvedId = $nameToId[$className] ?? null;
                    if (null !== $resolvedId) {
                        $clusterNodeIds[] = 'class:'.$resolvedId;
                    }
                }
            }

            if ([] !== $clusterNodeIds) {
                $clusters[] = [
                    'id' => 'package:'.$packageName,
                    'name' => $packageName,
                    'nodeIds' => $clusterNodeIds,
                ];
            }
        }

        return ['nodes' => $nodes, 'clusters' => $clusters];
    }
}
