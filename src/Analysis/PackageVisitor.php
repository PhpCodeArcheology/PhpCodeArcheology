<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\Collections\PackageNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class PackageVisitor implements NodeVisitor, VisitorInterface, InitializableVisitorInterface, ConfigAwareVisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $packages = [];

    private string $fileNamespace = '';

    private string $filePackage = '';

    /** @var array<string, array{classes: array<int, string>, functions: array<int, string>, files: array<int, string>}> */
    private array $packageData = [];

    private Config $config;

    public function init(): void
    {
        $this->writer->setCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            new PackageNameCollection(),
            'packages'
        );

        $this->registry->createMetricCollection(
            MetricCollectionTypeEnum::PackageCollection,
            ['name' => '_global'],
        );

        $this->initCollections('_global');
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->fileNamespace = '_global';
        $this->packageData = [];
        $this->getCurrentPackageMetric('_global');

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        if (!$node instanceof Node\Stmt\Namespace_) {
            return null;
        }

        $namespace = (string) $node->name;

        $packageSize = $this->config->get('packageSize');
        if (is_int($packageSize)) {
            $namespaceParts = explode('\\', $namespace);
            if (count($namespaceParts) > $packageSize) {
                $newArray = [];
                for ($i = 0; $i < $packageSize; ++$i) {
                    $newArray[] = $namespaceParts[$i];
                }
                $namespace = implode('\\', $newArray);
            }
        }

        $this->fileNamespace = $namespace;
        $this->getCurrentPackageMetric($namespace);
        $this->setFileCount($namespace);

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();

                $package = $this->detectPackage($node);
                $this->getCurrentPackageMetric($package);

                $this->writer->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    $package,
                    MetricKey::PACKAGE
                );

                $this->packageData[$package]['classes'][] = $className;

                $this->writer->setCollectionDataUnique(
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

                $this->writer->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => (string) $node->namespacedName,
                    ],
                    $package,
                    MetricKey::PACKAGE
                );

                $this->packageData[$package]['functions'][] = $functionName;

                $this->writer->setCollectionDataUnique(
                    MetricCollectionTypeEnum::PackageCollection,
                    ['name' => $package],
                    'functions',
                    null,
                    $functionName
                );

                break;
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->writer->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->fileNamespace,
            MetricKey::NAMESPACE
        );

        $this->writer->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->filePackage,
            MetricKey::PACKAGE
        );

        $this->writer->setCollectionDataUnique(
            MetricCollectionTypeEnum::PackageCollection,
            ['name' => $this->fileNamespace],
            'files',
            null,
            $this->path
        );

        return null;
    }

    private function getCurrentPackageMetric(?string $packageName = null): void
    {
        if (!$packageName) {
            $packageName = $this->fileNamespace;
        }

        if (!in_array($packageName, $this->packages)) {
            $this->packages[] = $packageName;

            $packageCollection = $this->registry->createMetricCollection(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
            );

            $this->writer->setCollectionDataOrCreateEmptyCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                'packages',
                (string) $packageCollection->getIdentifier(),
                $packageName,
                new PackageNameCollection()
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
        $docBlock = $docBlock instanceof \PhpParser\Comment\Doc ? $docBlock->getText() : '';

        if (preg_match('/^\s*\* @package (.*)/m', $docBlock, $matches)) {
            $package = trim($matches[1]);
        }

        if (preg_match('/^\s*\* @subpackage (.*)/m', $docBlock, $matches)) {
            $package = $package.'\\'.trim($matches[1]);
        }

        return $package;
    }

    public function initCollections(string $packageName): void
    {
        $collections = [
            'classes' => new ClassNameCollection(),
            'functions' => new FunctionNameCollection(),
            'files' => new FileNameCollection(),
        ];

        foreach ($collections as $collectionName => $collectionObject) {
            $this->writer->setCollection(
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
