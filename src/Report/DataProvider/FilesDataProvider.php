<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $files = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'files'
        );

        $classes = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $functions = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $fileClasses = [];
        $fileFunctions = [];
        foreach ($files as $fileKey => $_) {
            $classKeyNamePairs = $this->repository->loadCollection(
                null,
                $fileKey,
                'classes'
            )?->getAsArray() ?? [];

            $fileClasses[$fileKey] = array_intersect_key($classes, $classKeyNamePairs);

            $functionKeyNamePairs = $this->repository->loadCollection(
                null,
                $fileKey,
                'functions'
            )?->getAsArray() ?? [];

            $fileFunctions[$fileKey] = array_intersect_key($functions, $functionKeyNamePairs);
        }

        $listMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $detailMetrics = $this->repository->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $classListMetrics = $this->repository->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $functionListMetrics = $this->repository->getListMetricsByCollectionType(
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
