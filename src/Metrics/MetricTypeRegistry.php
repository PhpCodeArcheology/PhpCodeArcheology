<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics;

use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;

class MetricTypeRegistry
{
    /** @var array<string, array<MetricType>> */
    private array $metricTypes = [];

    /** @var MetricType[] */
    private array $metricTypeMap = [];

    public function register(): void
    {
        $rawData = require __DIR__.'/../../data/metric-types.php';
        if (!is_array($rawData)) {
            return;
        }

        foreach ($rawData as $metricTypeData) {
            if (!is_array($metricTypeData)) {
                continue;
            }

            $typeValue = $metricTypeData['type'] ?? null;
            if (is_string($typeValue) && 'storage' === $typeValue) {
                $keyValue = $metricTypeData['key'] ?? null;
                if (!is_string($keyValue)) {
                    continue;
                }
                $metricType = MetricType::fromKey($keyValue);
                $metricType->setValueType(MetricValueType::Storage);
                $this->addMetricType($metricType, MetricCollectionTypeEnum::ProjectCollection);
                continue;
            }

            $collectionsRaw = $metricTypeData['collections'] ?? null;
            if (!is_array($collectionsRaw)) {
                continue;
            }
            unset($metricTypeData['collections']);

            $metricType = MetricType::fromArray($metricTypeData);

            foreach ($collectionsRaw as $collection) {
                if (!$collection instanceof MetricCollectionTypeEnum) {
                    continue;
                }
                $this->addMetricType($metricType, $collection);
            }
        }
    }

    private function addMetricType(MetricType $metricType, MetricCollectionTypeEnum $collectionType): void
    {
        if (!isset($this->metricTypes[$collectionType->name])) {
            $this->metricTypes[$collectionType->name] = [];
        }

        if (in_array($metricType, $this->metricTypes[$collectionType->name])) {
            return;
        }

        $this->metricTypes[$collectionType->name][] = $metricType;

        if (in_array($metricType, $this->metricTypeMap)) {
            return;
        }

        $this->metricTypeMap[$metricType->getKey()] = $metricType;
    }

    /** @return MetricType[] */
    public function getByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array
    {
        if (!isset($this->metricTypes[$collectionType->name])) {
            return [];
        }

        return array_filter($this->metricTypes[$collectionType->name], function (MetricType $metricType) use ($visibility, $showEverywhere): bool {
            if (is_array($metricType->getVisibility())) {
                return in_array($visibility, $metricType->getVisibility()) || ($showEverywhere && in_array(MetricVisibility::ShowEverywhere, $metricType->getVisibility()));
            }

            return $metricType->getVisibility() === $visibility || (MetricVisibility::ShowEverywhere === $metricType->getVisibility() && $showEverywhere);
        });
    }

    /** @return MetricType[] */
    public function getDetailMetrics(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getByCollectionTypeAndVisibility($collectionType, MetricVisibility::ShowDetails);
    }

    /** @return MetricType[] */
    public function getListMetrics(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getByCollectionTypeAndVisibility($collectionType, MetricVisibility::ShowList);
    }

    public function applyTypeToValue(MetricValue $metricValue): void
    {
        $metricType = $this->metricTypeMap[$metricValue->getMetricTypeKey()] ?? null;
        if (null === $metricType) {
            throw new \RuntimeException('Unknown metric type key: '.$metricValue->getMetricTypeKey());
        }
        $metricValue->setMetricType($metricType);
    }
}
