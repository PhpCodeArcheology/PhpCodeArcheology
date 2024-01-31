<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class GlobalsVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * Globals to track
     */
    const GLOBALS = [
        'GLOBALS' => 0,
        '_SERVER' => 0,
        '_GET' => 0,
        '_POST' => 0,
        '_FILES' => 0,
        '_COOKIE' => 0,
        '_SESSION' => 0,
        '_REQUEST' => 0,
        '_ENV' => 0,
    ];

    /**
     * @var string[]
     */
    private array $currentFunctionName = [];

    /**
     * @var string[]
     */
    private array $currentClassName = [];

    /**
     * @var string[]
     */
    private array $currentMethodName = [];

    private array $superglobals = [];

    private array $superglobalsFunction = [];

    private array $superglobalsClass = [];

    private array $variableMap = [];

    private array $functionVariableMap = [];

    private array $classVariableMap = [];

    private array $constantMap = [];

    private array $functionConstantMap = [];

    private array $classConstantMap = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->superglobals = self::GLOBALS;
        $this->variableMap = [];
        $this->currentClassName = [];
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
            case $node instanceof Node\Stmt\ClassMethod:
                $functionName = strval($node->namespacedName ?? $node->name);

                $this->superglobalsFunction[$functionName] = self::GLOBALS;
                $this->functionVariableMap[$functionName] = [];
                $this->functionConstantMap[$functionName] = [];

                if ($node instanceof Node\Stmt\Function_) {
                    $this->currentFunctionName[] = $functionName;
                    break;
                }

                $this->currentMethodName[] = $functionName;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();

                $this->superglobalsClass[$className] = self::GLOBALS;
                $this->classVariableMap[$className] = [];
                $this->classConstantMap[$className] = [];

                $this->currentClassName[] = $className;
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        $className = end($this->currentClassName);
        $functionName = end($this->currentFunctionName);
        $methodName = end($this->currentMethodName);

        $this->countVariableNodes($node, $functionName, false, true);
        $this->countConstantNodes($node, $functionName, false, true);
        $this->countVariableNodes($node, $methodName, $className);
        $this->countConstantNodes($node, $methodName, $className);

        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);

                $this->repository->saveMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    [
                        'superglobals' => $this->superglobalsFunction[$functionName],
                        'variables' => $this->functionVariableMap[$functionName],
                        'constants' => $this->functionConstantMap[$functionName],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentMethodName);

                $this->repository->saveMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    [
                        'superglobals' => $this->superglobalsFunction[$methodName],
                        'variables' => $this->functionVariableMap[$methodName],
                        'constants' => $this->functionConstantMap[$methodName],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);

                $this->repository->saveMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    [
                        'superglobals' => $this->superglobalsClass[$className],
                        'variables' => $this->classVariableMap[$className],
                        'constants' => $this->classConstantMap[$className],
                    ]
                );
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            [
                'superglobals' => $this->superglobals,
                'variables' => $this->variableMap,
                'constants' => $this->constantMap,
            ]
        );
    }

    /**
     * @param Node $node
     * @param false|string $functionName
     * @param false|string $className
     * @return void
     */
    public function countVariableNodes(Node $node, false|string $functionName, false|string $className, bool $countGlobal = false): void
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            if (in_array($node->name, array_keys($this->superglobals))) {
                if ($countGlobal === false) {
                    ++$this->superglobals[$node->name];
                }

                if ($functionName) {
                    ++$this->superglobalsFunction[$functionName][$node->name];
                }

                if ($className) {
                    ++$this->superglobalsClass[$className][$node->name];
                }
            }
            elseif (!in_array($node->name, ['this', 'self'])) {
                if ($countGlobal) {
                    if (!isset($this->variableMap[$node->name])) {
                        $this->variableMap[$node->name] = 0;
                    }
                    ++$this->variableMap[$node->name];
                }

                if ($functionName) {
                    if (!isset($this->functionVariableMap[$functionName][$node->name])) {
                        $this->functionVariableMap[$functionName][$node->name] = 0;
                    }
                    ++$this->functionVariableMap[$functionName][$node->name];
                }

                if ($className) {
                    if (!isset($this->classVariableMap[$className][$node->name])) {
                        $this->classVariableMap[$className][$node->name] = 0;
                    }
                    ++$this->classVariableMap[$className][$node->name];
                }
            }
        }
    }

    /**
     * @param Node $node
     * @param false|string $functionName
     * @param false|string $className
     * @return void
     */
    public function countConstantNodes(Node $node, false|string $functionName, false|string $className): void
    {
        if ($node instanceof Node\Expr\ConstFetch) {
            $constantName = $node->name->toString();

            if (!in_array(strtolower($constantName), ['true', 'false', 'null']) && !defined($constantName)) {
                if (!isset($this->constantMap[$constantName])) {
                    $this->constantMap[$constantName] = 0;
                }
                ++$this->constantMap[$constantName];

                if ($functionName) {
                    if (!isset($this->functionConstantMap[$functionName][$constantName])) {
                        $this->functionConstantMap[$functionName][$constantName] = 0;
                    }
                    ++$this->functionConstantMap[$functionName][$constantName];
                }

                if ($className) {
                    if (!isset($this->classConstantMap[$className][$constantName])) {
                        $this->classConstantMap[$className][$constantName] = 0;
                    }
                    ++$this->classConstantMap[$className][$constantName];
                }
            }
        }
    }
}
