<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class RuntimeComplexityVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $currentClassName = [];
    /** @var array<int, string> */
    private array $currentFunctionName = [];

    private int $fileMaxLoopDepth = 0;
    /** @var array<string, int> */
    private array $classMaxLoopDepth = [];
    /** @var array<string, int> */
    private array $functionMaxLoopDepth = [];

    private int $currentLoopDepth = 0;

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->fileMaxLoopDepth = 0;
        $this->classMaxLoopDepth = [];
        $this->functionMaxLoopDepth = [];
        $this->currentLoopDepth = 0;

        return null;
    }

    public function enterNode(Node $node): int|Node|null
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
                if (false === $className) {
                    break;
                }
                $key = $className.'::'.(string) $node->name;
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

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }
                $complexity = $this->depthToComplexity($this->classMaxLoopDepth[$className] ?? 0);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    $complexity,
                    MetricKey::ESTIMATED_RUNTIME_COMPLEXITY
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $key = array_pop($this->currentFunctionName);
                if (null === $key) {
                    break;
                }
                $parts = explode('::', $key, 2);
                $complexity = $this->depthToComplexity($this->functionMaxLoopDepth[$key] ?? 0);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $parts[0], 'name' => $parts[1] ?? ''],
                    $complexity,
                    MetricKey::ESTIMATED_RUNTIME_COMPLEXITY
                );
                $this->currentLoopDepth = 0;
                break;

            case $node instanceof Node\Stmt\Function_:
                $key = array_pop($this->currentFunctionName);
                if (null === $key) {
                    break;
                }
                $complexity = $this->depthToComplexity($this->functionMaxLoopDepth[$key] ?? 0);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $key],
                    $complexity,
                    MetricKey::ESTIMATED_RUNTIME_COMPLEXITY
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

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->depthToComplexity($this->fileMaxLoopDepth),
            MetricKey::ESTIMATED_RUNTIME_COMPLEXITY
        );

        return null;
    }

    private function updateMaxDepth(): void
    {
        $this->fileMaxLoopDepth = max($this->fileMaxLoopDepth, $this->currentLoopDepth);

        if (count($this->currentClassName) > 0) {
            $className = end($this->currentClassName);
            $this->classMaxLoopDepth[$className] = max($this->classMaxLoopDepth[$className] ?? 0, $this->currentLoopDepth);
        }

        if (count($this->currentFunctionName) > 0) {
            $key = end($this->currentFunctionName);
            $this->functionMaxLoopDepth[$key] = max($this->functionMaxLoopDepth[$key] ?? 0, $this->currentLoopDepth);
        }
    }

    private function depthToComplexity(int $depth): string
    {
        return match (true) {
            $depth >= 3 => 'O(n³+)',
            2 === $depth => 'O(n²)',
            1 === $depth => 'O(n)',
            default => 'O(1)',
        };
    }
}
