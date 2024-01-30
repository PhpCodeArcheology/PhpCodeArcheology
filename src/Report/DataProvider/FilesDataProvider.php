<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $files = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'files'
        );

        $classes = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $functions = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $fileClasses = [];
        $fileFunctions = [];
        foreach ($files as $fileKey => $_) {
            $classKeyNamePairs = $this->metricsController->getCollectionByIdentifierString(
                $fileKey,
                'classes'
            )?->getAsArray() ?? [];

            $fileClasses[$fileKey] = array_intersect_key($classes, $classKeyNamePairs);

            $functionKeyNamePairs = $this->metricsController->getCollectionByIdentifierString(
                $fileKey,
                'functions'
            )?->getAsArray() ?? [];

            $fileFunctions[$fileKey] = array_intersect_key($functions, $functionKeyNamePairs);
        }

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $classListMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $functionListMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $templateData = [
            'files' => $files,
            'fileClasses' => $fileClasses,
            'fileFunctions' => $fileFunctions,
            'tableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $listMetrics),
            'functionTableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $functionListMetrics),
            'classTableHeaders' => array_map(function($metricType) {
                return $metricType->__toArray();
            }, $classListMetrics),
            'listMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $listMetrics),
            'detailMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $detailMetrics),
            'classListMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $classListMetrics),
            'functionListMetricKeys' => array_map(function($metricType) {
                return $metricType->getKey();
            }, $functionListMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
