<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Repository\RepositoryInterface;

class DataProviderFactory
{
    private array $data = [];

    public function __construct(
        private readonly RepositoryInterface $repository)
    {
        /** @noinspection PhpEmptyStatementInspection */
        foreach ($this->setMetricValues() as $_) {
            // Only runs the generator
        }
    }

    private function setMetricValues(): \Generator
    {
        foreach ($this->repository->getAllMetricCollections() as $metricCollection) {
            /** @noinspection PhpEmptyStatementInspection */
            foreach ($this->setMetricValuesInCollection($metricCollection) as $_) {
                // Only runs the generator
            }
            yield;
        }
    }

    private function setMetricValuesInCollection(MetricsCollectionInterface $metricsCollection): \Generator
    {
        foreach ($metricsCollection->getAll() as &$metricValue) {
            /**
             * @var MetricValue $metricValue
             */

            $this->repository->setMetricTypeToMetricValue($metricValue);

            yield;
        }
    }

    public function getProjectDataProvider(): ProjectDataProvider
    {
        return new ProjectDataProvider($this->repository);
    }

    public function getFilesDataProvider(): FilesDataProvider
    {
        return new FilesDataProvider($this->repository);
    }

    public function getClassDataProvider(): ClassDataProvider
    {
        return new ClassDataProvider($this->repository);
    }

    public function getPackagDataProvider(): PackagesDataProvider
    {
        return new PackagesDataProvider($this->repository);
    }

    public function getClassCouplingDataProvider(): ClassCouplingDataProvider
    {
        return new ClassCouplingDataProvider($this->repository);
    }

    public function getClassesChartDataProvider(): ClassesChartDataProvider
    {
        return new ClassesChartDataProvider($this->repository);
    }

    public function getFunctionDataProvider(): FunctionDataProvider
    {
        return new FunctionDataProvider($this->repository);
    }

    public function getProblemDataProvider(): ProblemDataProvider
    {
        return new ProblemDataProvider($this->repository);
    }

    private function predictProgrammingParadigm(): void
    {
        $classCount = $this->data['OverallClasses'];
        $functionCount = $this->data['OverallFunctions'];
        $methodCount = $this->data['OverallMethods'];

        $lloc = $this->data['OverallLloc'];
        $llocOutside = $this->data['OverallLlocOutside'];
        $OverallInsideMethodLloc = $this->data['OverallInsideMethodLloc'];
        $OverallInsideFuntionLloc = $this->data['OverallInsideFuntionLloc'];

        $maxCC = $this->data['OverallMaxCC'];
        $maxCCFile = $this->data['OverallMaxCCFile'];
        $maxCCClass = $this->data['OverallMaxCCClass'];
        $maxCCMethod = $this->data['OverallMaxCCMethod'];
        $maxCCFunction = $this->data['OverallMaxCCFunction'];

        $avgCC = $this->data['OverallAvgCC'];
        $avgCCFile = $this->data['OverallAvgCCFile'];
        $avgCCClass = $this->data['OverallAvgCCClass'];
        $avgCCMethod = $this->data['OverallAvgCCMethod'];
        $avgCCFunction = $this->data['OverallAvgCCFunction'];

        $methodsToFunctionsScore = $methodCount / ($functionCount + $methodCount);
        $llocToLlocOutsideScore = $llocOutside / $lloc;
        $methodsToFunctionsLlocScore = $OverallInsideMethodLloc / ($OverallInsideFuntionLloc + $OverallInsideMethodLloc);
        $cyclomaticComplexityScore = 1 / $avgCC;

        $weights = [
            'methodsToFunctions' => 0.2,
            'llocToLlocOutside' => 0.5,
            'methodsToFunctionsLloc' => 0.2,
            'cyclomaticComplexity' => 0.1
        ];

        $totalScore = ($methodsToFunctionsScore * $weights['methodsToFunctions']) +
            ($llocToLlocOutsideScore * $weights['llocToLlocOutside']) +
            ($methodsToFunctionsLlocScore * $weights['methodsToFunctionsLloc']) +
            ($cyclomaticComplexityScore * $weights['cyclomaticComplexity']);
    }
}
