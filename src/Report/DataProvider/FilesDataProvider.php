<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $files = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'files'
        );

        $classes = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $functions = $this->reader->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions'
        );

        $fileClasses = [];
        $fileFunctions = [];
        foreach (array_keys($files) as $fileKey) {
            $classKeyNamePairs = $this->reader->getCollectionByIdentifierString(
                $fileKey,
                'classes'
            )?->getAsArray() ?? [];

            $fileClasses[$fileKey] = array_intersect_key($classes, $classKeyNamePairs);

            $functionKeyNamePairs = $this->reader->getCollectionByIdentifierString(
                $fileKey,
                'functions'
            )?->getAsArray() ?? [];

            $fileFunctions[$fileKey] = array_intersect_key($functions, $functionKeyNamePairs);
        }

        $listMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $detailMetrics = $this->registry->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $classListMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::ClassCollection
        );

        $functionListMetrics = $this->registry->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FunctionCollection
        );

        $templateData = [
            'files' => $files,
            'fileClasses' => $fileClasses,
            'fileFunctions' => $fileFunctions,
            'tableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $listMetrics),
            'functionTableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $functionListMetrics),
            'classTableHeaders' => array_map(fn ($metricType) => $metricType->__toArray(), $classListMetrics),
            'listMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $listMetrics),
            'detailMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $detailMetrics),
            'classListMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $classListMetrics),
            'functionListMetricKeys' => array_map(fn ($metricType) => $metricType->getKey(), $functionListMetrics),
        ];

        $this->templateData = array_merge($this->templateData, $templateData);
    }
}
