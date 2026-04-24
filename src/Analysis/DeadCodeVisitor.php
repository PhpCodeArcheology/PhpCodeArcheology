<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class DeadCodeVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $currentClassName = [];

    /** @var array<string, string[]> className → [methodName, ...] */
    private array $privateMethods = [];

    /** @var array<string, string[]> className → [calledMethodName, ...] */
    private array $calledMethods = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClassName = [];
        $this->privateMethods = [];
        $this->calledMethods = [];

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->privateMethods[$className] = [];
                $this->calledMethods[$className] = [];
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                if (0 === count($this->currentClassName)) {
                    break;
                }
                $className = end($this->currentClassName);
                $methodName = (string) $node->name;

                // Skip magic methods
                if ($node->isPrivate() && !str_starts_with($methodName, '__')) {
                    $this->privateMethods[$className][] = $methodName;
                }
                break;

                // Track $this->method() calls
            case $node instanceof Node\Expr\MethodCall:
                if (0 === count($this->currentClassName)) {
                    break;
                }
                $className = end($this->currentClassName);

                if ($node->var instanceof Node\Expr\Variable && 'this' === $node->var->name
                    && $node->name instanceof Node\Identifier) {
                    $this->calledMethods[$className][] = (string) $node->name;
                }
                break;

                // Track self::method() and static::method() calls
            case $node instanceof Node\Expr\StaticCall:
                if (0 === count($this->currentClassName)) {
                    break;
                }
                $className = end($this->currentClassName);

                if ($node->class instanceof Node\Name
                    && in_array($node->class->toString(), ['self', 'static'])
                    && $node->name instanceof Node\Identifier) {
                    $this->calledMethods[$className][] = (string) $node->name;
                }
                break;

                // Track $this->method used as callable (e.g., [$this, 'method'])
            case $node instanceof Node\Expr\Array_:
                if (0 === count($this->currentClassName) || 2 !== count($node->items)) {
                    break;
                }
                $className = end($this->currentClassName);
                $first = $node->items[0]->value;
                $second = $node->items[1]->value;

                if ($first instanceof Node\Expr\Variable && 'this' === $first->name
                    && $second instanceof Node\Scalar\String_) {
                    $this->calledMethods[$className][] = $second->value;
                }
                break;
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $calledSet = array_unique($this->calledMethods[$className] ?? []);
                $unusedMethods = array_diff($this->privateMethods[$className] ?? [], $calledSet);

                $this->writer->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    [
                        MetricKey::UNUSED_PRIVATE_METHOD_COUNT => count($unusedMethods),
                        MetricKey::UNUSED_PRIVATE_METHODS => array_values($unusedMethods),
                    ]
                );
                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        return null;
    }
}
