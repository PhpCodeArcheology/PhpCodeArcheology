<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\PackageIdentifier;
use PhpCodeArch\Metrics\PackageMetrics\PackageMetrics;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class PackageVisitor implements NodeVisitor
{
    use VisitorTrait;

    private array $packages = ['_global'];

    private string $fileNamespace = '';

    private ?PackageMetrics $currentPackageMetric;

    public function beforeTraverse(array $nodes): void
    {
        $this->fileNamespace = '_global';
        $this->currentPackageMetric = null;
    }

    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->fileNamespace = (string) $node->name;
            $this->currentPackageMetric = $this->getCurrentPackageMetric();

            $this->setFileCount();
        }
    }

    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $package = $this->detectPackage($node);
            $this->currentPackageMetric = $this->getCurrentPackageMetric($package);

            $classMetric = ClassMetricsFactory::createFromMetricsByNodeAndPath($this->metrics, $node, $this->path);
            $classId = (string) $classMetric->getIdentifier();
            $classMetric->set('package', $package);
            $this->metrics->set($classId, $classMetric);

            $classes = $this->currentPackageMetric->get('classes') ?? [];
            $classes[] = $classId;
            $this->currentPackageMetric->set('classes', $classes);
            $this->metrics->set((string) $this->currentPackageMetric->getIdentifier(), $this->currentPackageMetric);
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $package = $this->detectPackage($node);
            $this->currentPackageMetric = $this->getCurrentPackageMetric($package);

            $fnMetric = FunctionMetricsFactory::createFromMetricsByNameAndPath($this->metrics, $node->namespacedName, $this->path);
            $fnId = (string) $fnMetric->getIdentifier();
            $fnMetric->set('package', $this->fileNamespace);
            $this->metrics->set($fnId, $fnMetric);

            $functions = $this->currentPackageMetric->get('functions') ?? [];
            $functions[] = $fnId;

            $this->currentPackageMetric->set('functions', $functions);
            $this->metrics->set((string) $this->currentPackageMetric->getIdentifier(), $this->currentPackageMetric);
        }
    }

    public function afterTraverse(array $nodes): void
    {
        $this->currentPackageMetric = $this->getCurrentPackageMetric();
        $this->setFileCount();

        if ($this->currentPackageMetric->get('functions') === null) {
            $this->currentPackageMetric->set('functions', []);
        }

        if ($this->currentPackageMetric->get('classes') === null) {
            $this->currentPackageMetric->set('classes', []);
        }

        $fileId = (string)FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);
        $fileMetrics->set('namespace', $this->fileNamespace);

        $this->metrics->set($fileId, $fileMetrics);
        $this->metrics->set('packages', $this->packages);

        $this->metrics->set((string) $this->currentPackageMetric->getIdentifier(), $this->currentPackageMetric);
    }

    private function getCurrentPackageMetric(?string $packageName = null): PackageMetrics
    {
        if (! $packageName) {
            $packageName = $this->fileNamespace;
        }

        if ($this->currentPackageMetric !== null && $this->currentPackageMetric->getName() === $packageName) {
            return $this->currentPackageMetric;
        }

        $packageId = (string) PackageIdentifier::ofNamespace($packageName);
        if ($this->metrics->get($packageId) === null) {
            if (! in_array($packageName, $this->packages)) {
                $this->packages[] = $packageName;
            }

            return new PackageMetrics($packageId);
        }

        return $this->metrics->get($packageId);
    }

    private function setFileCount(): void
    {
        $files = $this->currentPackageMetric->get('files') ?? [];

        if (in_array($this->path, $files)) {
            return;
        }

        $files[] = $this->path;
        $this->currentPackageMetric->set('files', $files);
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

}
