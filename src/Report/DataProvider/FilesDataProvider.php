<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $files;

    public function gatherData(): void
    {
        $files = $this->reportDataContainer->get('files')->getAll();
        $classes = $this->reportDataContainer->get('classes')->getAll();
        $functions = $this->reportDataContainer->get('functions')->getAll();

        $files = array_map(function($file) use ($classes, $functions) {
            $file['errors'] = $this->metricsController->getCollectionByIdentifierString(
                $file['id'],
                'errors'
            )->getAsArray();

            list($fileListMetrics, $fileFunctions) = $this->getTableData(
                $file['id'],
                'functions',
                MetricCollectionTypeEnum::FunctionCollection,
                $functions
            );

            $file['functionTableHeaders'] = array_map(function($metricType) {
                return $metricType->__toArray();
            }, $fileListMetrics);

            $file['functions'] = $fileFunctions;

            list($classListMetrics, $fileClasses) = $this->getTableData(
                $file['id'],
                'classes',
                MetricCollectionTypeEnum::ClassCollection,
                $classes
            );

            $file['classTableHeaders'] = array_map(function($metricType) {
                return $metricType->__toArray();
            }, $classListMetrics);

            $file['classes'] = $fileClasses;

            return $file;
        }, $files);

        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $detailMetrics = $this->metricsController->getDetailMetricsByCollectionType(
            MetricCollectionTypeEnum::FileCollection
        );

        $files = $this->setDataFromMetricTypesAndArrayToArrayKey($files, $detailMetrics, 'detailData');
        $files = $this->setDataFromMetricTypesAndArrayToArrayKey($files, $listMetrics, 'listData');

        $this->templateData['files'] = $files;
        $this->templateData['tableHeaders'] = array_map(function($metricType) {
            return $metricType->__toArray();
        }, $listMetrics);

        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    function getTableData(string $identifierString, string $collectionKey, MetricCollectionTypeEnum $metricCollectionType, array $elements): array
    {
        $listMetrics = $this->metricsController->getListMetricsByCollectionType(
            $metricCollectionType
        );

        $keyAndNames = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            $collectionKey
        )?->getAsArray() ?? [];

        $ids = array_keys($keyAndNames);

        $tableData = array_filter($elements, function ($key) use ($ids) {
            return in_array($key, $ids);
        }, ARRAY_FILTER_USE_KEY);

        $tableData = $this->setDataFromMetricTypesAndArrayToArrayKey($tableData, $listMetrics, 'listData');
        return [$listMetrics, $tableData];
    }
}
