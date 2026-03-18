<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class RuntimeComplexityVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $currentClassName = [];
    private array $currentFunctionName = [];

    private int $fileMaxLoopDepth = 0;
    private array $classMaxLoopDepth = [];
    private array $functionMaxLoopDepth = [];

    private int $currentLoopDepth = 0;

    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->fileMaxLoopDepth = 0;
        $this->classMaxLoopDepth = [];
        $this->functionMaxLoopDepth = [];
        $this->currentLoopDepth = 0;
    }

    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->classMaxLoopDepth[$className] = 0;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $key = $className . '::' . (string) $node->name;
                $this->currentFunctionName[] = $key;
                $this->functionMaxLoopDepth[$key] = 0;
                $this->currentLoopDepth = 0;
                break;

            case $node instanceof Node\Stmt\Function_:
                $key = (string) $node->namespacedName;
                $this->currentFunctionName[] = $key;
                $this->functionMaxLoopDepth[$key] = 0;
                $this->currentLoopDepth = 0;
                break;

            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
                $this->currentLoopDepth++;
                $this->updateMaxDepth();
                break;
        }
    }

    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                $complexity = $this->depthToComplexity($this->classMaxLoopDepth[$className]);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    $complexity,
                    'estimatedRuntimeComplexity'
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $key = array_pop($this->currentFunctionName);
                $parts = explode('::', $key, 2);
                $complexity = $this->depthToComplexity($this->functionMaxLoopDepth[$key]);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $parts[0], 'name' => $parts[1]],
                    $complexity,
                    'estimatedRuntimeComplexity'
                );
                $this->currentLoopDepth = 0;
                break;

            case $node instanceof Node\Stmt\Function_:
                $key = array_pop($this->currentFunctionName);
                $complexity = $this->depthToComplexity($this->functionMaxLoopDepth[$key]);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $key],
                    $complexity,
                    'estimatedRuntimeComplexity'
                );
                $this->currentLoopDepth = 0;
                break;

            case $node instanceof Node\Stmt\For_:
            case $node instanceof Node\Stmt\Foreach_:
            case $node instanceof Node\Stmt\While_:
            case $node instanceof Node\Stmt\Do_:
                $this->currentLoopDepth--;
                break;
        }
    }

    public function afterTraverse(array $nodes): void
    {
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->depthToComplexity($this->fileMaxLoopDepth),
            'estimatedRuntimeComplexity'
        );
    }

    private function updateMaxDepth(): void
    {
        $this->fileMaxLoopDepth = max($this->fileMaxLoopDepth, $this->currentLoopDepth);

        if (count($this->currentClassName) > 0) {
            $className = end($this->currentClassName);
            $this->classMaxLoopDepth[$className] = max($this->classMaxLoopDepth[$className], $this->currentLoopDepth);
        }

        if (count($this->currentFunctionName) > 0) {
            $key = end($this->currentFunctionName);
            $this->functionMaxLoopDepth[$key] = max($this->functionMaxLoopDepth[$key], $this->currentLoopDepth);
        }
    }

    private function depthToComplexity(int $depth): string
    {
        return match (true) {
            $depth >= 3 => 'O(n³+)',
            $depth === 2 => 'O(n²)',
            $depth === 1 => 'O(n)',
            default => 'O(1)',
        };
    }
}
