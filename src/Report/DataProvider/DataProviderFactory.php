<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;

class DataProviderFactory
{
    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsRegistryInterface $registry,
    ) {
        foreach ($this->setMetricValues() as $_) {
            // Only runs the generator
        }
    }

    private function setMetricValues(): \Generator
    {
        foreach ($this->registry->getAllCollections() as $metricCollection) {
            foreach ($this->setMetricValuesInCollection($metricCollection) as $_) {
                // Only runs the generator
            }
            yield;
        }
    }

    private function setMetricValuesInCollection(MetricsCollectionInterface $metricsCollection): \Generator
    {
        foreach ($metricsCollection->getAll() as &$metricValue) {
            /*
             * @var MetricValue $metricValue
             */

            $this->registry->setMetricTypeToMetricValue($metricValue);

            yield;
        }
    }

    public function getProjectDataProvider(): ProjectDataProvider
    {
        return new ProjectDataProvider($this->reader, $this->registry);
    }

    public function getFilesDataProvider(): FilesDataProvider
    {
        return new FilesDataProvider($this->reader, $this->registry);
    }

    public function getClassDataProvider(): ClassDataProvider
    {
        return new ClassDataProvider($this->reader, $this->registry);
    }

    public function getPackageDataProvider(): PackagesDataProvider
    {
        return new PackagesDataProvider($this->reader, $this->registry);
    }

    public function getClassCouplingDataProvider(): ClassCouplingDataProvider
    {
        return new ClassCouplingDataProvider($this->reader, $this->registry);
    }

    public function getClassesChartDataProvider(): ClassesChartDataProvider
    {
        return new ClassesChartDataProvider($this->reader, $this->registry);
    }

    public function getFunctionDataProvider(): FunctionDataProvider
    {
        return new FunctionDataProvider($this->reader, $this->registry);
    }

    public function getProblemDataProvider(): ProblemDataProvider
    {
        return new ProblemDataProvider($this->reader, $this->registry);
    }

    public function getGitDataProvider(): GitDataProvider
    {
        return new GitDataProvider($this->reader, $this->registry);
    }

    public function getRefactoringPriorityDataProvider(): RefactoringPriorityDataProvider
    {
        return new RefactoringPriorityDataProvider($this->reader, $this->registry);
    }

    public function getTestsDataProvider(): TestsDataProvider
    {
        return new TestsDataProvider($this->reader, $this->registry);
    }

    public function getGraphDataProvider(): GraphDataProvider
    {
        return new GraphDataProvider($this->reader, $this->registry);
    }

    public function getHistoryDataProvider(string $historyFile): HistoryDataProvider
    {
        $provider = new HistoryDataProvider($this->reader, $this->registry);
        $provider->setHistoryFile($historyFile);

        return $provider;
    }
}
