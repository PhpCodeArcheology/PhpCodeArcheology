<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Manager;

class MetricsManager
{
    /**
     * @var MetricCategory[]
     */
    private array $categories = [];

    /**
     * @var array<string, array<MetricType>>
     */
    private array $metricTypes = [];

    /**
     * @var MetricType[]
     */
    private array $metricTypeMap = [];

    /**
     * @param MetricCategory $category
     * @return void
     */
    public function addCategory(MetricCategory $category): void
    {
        if (in_array($category, $this->categories)) {
            return;
        }

        $this->categories[$category->getKey()] = $category;
        $this->metricTypes[$category->getKey()] = [];
    }

    /**
     * @param MetricCategory[] $categories
     * @return void
     */
    public function addCategories(array $categories): void
    {
        foreach ($categories as $category) {
            $this->addCategory($category);
        }
    }

    /**
     * @param MetricType $metricType
     * @param MetricCategory $category
     * @return void
     */
    public function addMetricType(MetricType $metricType, MetricCategory $category): void
    {
        $this->addCategory($category);

        if (in_array($metricType, $this->metricTypes[$category->getKey()])) {
            return;
        }

        $this->metricTypes[$category->getKey()][] = $metricType;

        if (in_array($metricType, $this->metricTypeMap)) {
            return;
        }

        $this->metricTypeMap[$metricType->getKey()] = $metricType;
    }

    /**
     * @param MetricCategory $category
     * @return array|MetricType[]
     */
    public function getMetricTypesOfCategory(MetricCategory $category): array
    {
        if (! isset($this->metricTypes[$category->getKey()])) {
            return [];
        }

        return $this->metricTypes[$category->getKey()];
    }

    /**
     * @param string $categoryName
     * @param int $visibility
     * @return MetricType[]
     */
    public function getMetricsByCategoryNameAndVisibility(string $categoryName, int $visibility): array
    {
        $searchCategory = MetricCategory::ofName($categoryName);

        if (! isset($this->metricTypes[$searchCategory->getKey()])) {
            return [];
        }

        return array_filter($this->metricTypes[$searchCategory->getKey()], function($metricType) use ($visibility) {
           return $metricType->getVisibility() === $visibility || $metricType->getVisibility() === MetricType::SHOW_EVERYWHERE;
        });
    }

    public function getDetailMetricsByCategoryName(string $categoryName): array
    {
        return $this->getMetricsByCategoryNameAndVisibility($categoryName, MetricType::SHOW_IN_DETAILS);
    }

    public function getListMetricsByCategoryName(string $categoryName)
    {
        return $this->getMetricsByCategoryNameAndVisibility($categoryName, MetricType::SHOW_IN_LIST);
    }

    public function getMetricTypeByKey(string $key): ?MetricType
    {
        if (! isset($this->metricTypeMap[$key])) {
            return null;
        }

        return $this->metricTypeMap[$key];
    }

    public function getMetricTypesByKeys(array $keys): array
    {
        $types = [];

        foreach ($keys as $key) {
            $types[$key] = $this->getMetricTypeByKey($key);
        }

        return $types;
    }
}
