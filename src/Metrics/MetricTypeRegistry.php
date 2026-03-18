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
        $metricTypes = require __DIR__ . '/../../data/metric-types.php';

        foreach ($metricTypes as $metricTypeArray) {
            if (isset($metricTypeArray['type']) && $metricTypeArray['type'] === 'storage') {
                $metricType = MetricType::fromKey($metricTypeArray['key']);
                $metricType->setValueType(MetricValueType::Storage);
                $this->addMetricType($metricType, MetricCollectionTypeEnum::ProjectCollection);
                continue;
            }

            $collections = array_pop($metricTypeArray);
            $metricType = MetricType::fromArray($metricTypeArray);

            foreach ($collections as $collection) {
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

    public function getByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array
    {
        if (!isset($this->metricTypes[$collectionType->name])) {
            return [];
        }

        return array_filter($this->metricTypes[$collectionType->name], function ($metricType) use ($visibility, $showEverywhere) {
            if (is_array($metricType->getVisibility())) {
                return in_array($visibility, $metricType->getVisibility()) || ($showEverywhere && in_array(MetricVisibility::ShowEverywhere, $metricType->getVisibility()));
            }

            return $metricType->getVisibility() === $visibility || ($metricType->getVisibility() === MetricVisibility::ShowEverywhere && $showEverywhere);
        });
    }

    public function getDetailMetrics(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getByCollectionTypeAndVisibility($collectionType, MetricVisibility::ShowDetails);
    }

    public function getListMetrics(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getByCollectionTypeAndVisibility($collectionType, MetricVisibility::ShowList);
    }

    public function applyTypeToValue(MetricValue $metricValue): void
    {
        $metricType = $this->metricTypeMap[$metricValue->getMetricTypeKey()];
        $metricValue->setMetricType($metricType);
    }
}
