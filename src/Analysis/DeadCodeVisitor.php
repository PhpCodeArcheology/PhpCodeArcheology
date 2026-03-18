<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class DeadCodeVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $currentClassName = [];

    /** @var array<string, string[]> className → [methodName, ...] */
    private array $privateMethods = [];

    /** @var array<string, string[]> className → [calledMethodName, ...] */
    private array $calledMethods = [];

    /** @var array<string, array<string, string>> className → [methodName → methodIdentifier] */
    private array $methodIdentifiers = [];

    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->privateMethods = [];
        $this->calledMethods = [];
        $this->methodIdentifiers = [];
    }

    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->privateMethods[$className] = [];
                $this->calledMethods[$className] = [];
                $this->methodIdentifiers[$className] = [];
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                if (count($this->currentClassName) === 0) {
                    break;
                }
                $className = end($this->currentClassName);
                $methodName = (string) $node->name;

                if ($node->isPrivate()) {
                    // Skip magic methods
                    if (!str_starts_with($methodName, '__')) {
                        $this->privateMethods[$className][] = $methodName;
                    }
                }
                break;

            // Track $this->method() calls
            case $node instanceof Node\Expr\MethodCall:
                if (count($this->currentClassName) === 0) {
                    break;
                }
                $className = end($this->currentClassName);

                if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier) {
                    $this->calledMethods[$className][] = (string) $node->name;
                }
                break;

            // Track self::method() and static::method() calls
            case $node instanceof Node\Expr\StaticCall:
                if (count($this->currentClassName) === 0) {
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
                if (count($this->currentClassName) === 0 || count($node->items) !== 2) {
                    break;
                }
                $className = end($this->currentClassName);
                $first = $node->items[0]?->value;
                $second = $node->items[1]?->value;

                if ($first instanceof Node\Expr\Variable && $first->name === 'this'
                    && $second instanceof Node\Scalar\String_) {
                    $this->calledMethods[$className][] = $second->value;
                }
                break;
        }
    }

    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);

                $calledSet = array_unique($this->calledMethods[$className] ?? []);
                $unusedMethods = array_diff($this->privateMethods[$className] ?? [], $calledSet);

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    [
                        'unusedPrivateMethodCount' => count($unusedMethods),
                        'unusedPrivateMethods' => array_values($unusedMethods),
                    ]
                );
                break;
        }
    }

    public function afterTraverse(array $nodes): void
    {
    }
}
