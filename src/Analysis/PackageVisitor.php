<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\Collections\PackageNameCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class PackageVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $packages = [];

    private string $fileNamespace = '';

    private string $filePackage = '';

    private ?PackageMetricsCollection $currentPackageMetric;

    private array $packageData = [];

    private Config $config;

    public function init(): void
    {
        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            new PackageNameCollection(),
            'packages'
        );

        $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::PackageCollection,
            ['name' => '_global'],
        );

        $this->initCollections('_global');
    }

    public function beforeTraverse(array $nodes): void
    {
        $this->fileNamespace = '_global';
        $this->packageData = [];
        $this->getCurrentPackageMetric('_global');
    }

    public function enterNode(Node $node): void
    {
        if (! $node instanceof Node\Stmt\Namespace_) {
            return;
        }

        $namespace = (string) $node->name;

        $packageSize = $this->config->get('packageSize');
        if (is_int($packageSize)) {
            $namespaceParts = explode('\\', $namespace);
            if (count($namespaceParts) > $packageSize) {
                $newArray = [];
                for ($i = 0; $i < $packageSize; $i ++) {
                    $newArray[] = $namespaceParts[$i];
                }
                $namespace = implode('\\', $newArray);
            }
        }

        $this->fileNamespace = $namespace;
        $this->getCurrentPackageMetric($namespace);
        $this->setFileCount($namespace);
    }

    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();

                $package = $this->detectPackage($node);
                $this->getCurrentPackageMetric($package);

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $package,
                    'package'
                );

                $this->packageData[$package]['classes'][] = ClassName::ofNode($node)->__toString();

                $this->metricsController->setCollectionDataUnique(
                    MetricCollectionTypeEnum::PackageCollection,
                    ['name' => $package],
                    'classes',
                    null,
                    $className
                );

                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $package = $this->detectPackage($node);
                $this->getCurrentPackageMetric($package);

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => (string) $node->namespacedName,
                    ],
                    $package,
                    'package'
                );

                $this->packageData[$package]['functions'][] = $functionName;

                $this->metricsController->setCollectionDataUnique(
                    MetricCollectionTypeEnum::PackageCollection,
                    ['name' => $package],
                    'functions',
                    null,
                    $functionName
                );

                break;
        }

    }

    public function afterTraverse(array $nodes): void
    {
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->fileNamespace,
            'namespace'
        );

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->filePackage,
            'package'
        );

        $this->metricsController->setCollectionDataUnique(
            MetricCollectionTypeEnum::PackageCollection,
            ['name' => $this->fileNamespace],
            'files',
            null,
            $this->path
        );
    }

    private function getCurrentPackageMetric(?string $packageName = null): void
    {
        if (! $packageName) {
            $packageName = $this->fileNamespace;
        }

        if (! in_array($packageName, $this->packages)) {
            $this->packages[] = $packageName;

            $this->metricsController->setCollectionDataOrCreateEmptyCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                'packages',
                null,
                $packageName,
                new PackageNameCollection()
            );

            $this->metricsController->createMetricCollection(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
            );

            $this->initCollections($packageName);
        }

        if (isset($this->packageData[$packageName])) {
            return;
        }

        $this->packageData[$packageName] = [
            'classes' => [],
            'functions' => [],
            'files' => [],
        ];

        $this->filePackage = $packageName;
    }

    private function setFileCount(string $package): void
    {
        $files = $this->packageData[$package]['files'];

        if (in_array($this->path, $files)) {
            return;
        }

        $this->packageData[$package]['files'][] = $this->path;
    }

    private function detectPackage(Node $node): string
    {
        $package = $this->fileNamespace;

        $docBlock = $node->getDocComment();
        $docBlock = $docBlock ? $docBlock->getText() : '';

        if (preg_match('/^\s*\* @package (.*)/m', $docBlock, $matches)) {
            $package = trim($matches[1]);
        }

        if (preg_match('/^\s*\* @subpackage (.*)/m', $docBlock, $matches)) {
            $package = $package . '\\' . trim($matches[1]);
        }

        return $package;
    }

    /**
     * @param string|null $packageName
     * @return void
     */
    public function initCollections(?string $packageName): void
    {
        $collections = [
            'classes' => new ClassNameCollection(),
            'functions' => new FunctionNameCollection(),
            'files' => new FileNameCollection(),
        ];

        foreach ($collections as $collectionName => $collectionObject) {
            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $collectionObject,
                $collectionName
            );
        }
    }

    public function injectConfig(Config $config): void
    {
        $this->config = $config;
    }
}
