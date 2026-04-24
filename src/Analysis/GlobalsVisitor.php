<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class GlobalsVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * Globals to track.
     */
    public const GLOBALS = [
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
     * @var array<int, string>
     */
    private array $currentFunctionName = [];

    /**
     * @var array<int, string>
     */
    private array $currentClassName = [];

    /**
     * @var array<int, string>
     */
    private array $currentMethodName = [];

    /** @var array<string, int> */
    private array $superglobals = [];

    /** @var array<string, array<string, int>> */
    private array $superglobalsFunction = [];

    /** @var array<string, array<string, int>> */
    private array $superglobalsClass = [];

    /** @var array<string, int> */
    private array $variableMap = [];

    /** @var array<string, array<string, int>> */
    private array $functionVariableMap = [];

    /** @var array<string, array<string, int>> */
    private array $classVariableMap = [];

    /** @var array<string, int> */
    private array $constantMap = [];

    /** @var array<string, array<string, int>> */
    private array $functionConstantMap = [];

    /** @var array<string, array<string, int>> */
    private array $classConstantMap = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->superglobals = self::GLOBALS;
        $this->superglobalsFunction = [];
        $this->superglobalsClass = [];
        $this->variableMap = [];
        $this->functionVariableMap = [];
        $this->classVariableMap = [];
        $this->constantMap = [];
        $this->functionConstantMap = [];
        $this->classConstantMap = [];
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->currentMethodName = [];

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $this->superglobalsFunction[$functionName] = self::GLOBALS;
                $this->functionVariableMap[$functionName] = [];
                $this->functionConstantMap[$functionName] = [];
                $this->currentFunctionName[] = $functionName;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $methodName = (string) $node->name;

                $this->superglobalsFunction[$methodName] = self::GLOBALS;
                $this->functionVariableMap[$methodName] = [];
                $this->functionConstantMap[$methodName] = [];
                $this->currentMethodName[] = $methodName;
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

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        $className = count($this->currentClassName) > 0 ? end($this->currentClassName) : false;
        $functionName = count($this->currentFunctionName) > 0 ? end($this->currentFunctionName) : false;
        $methodName = count($this->currentMethodName) > 0 ? end($this->currentMethodName) : false;

        $this->countVariableNodes($node, $functionName, false, true);
        $this->countConstantNodes($node, $functionName, false);
        $this->countVariableNodes($node, $methodName, $className);
        $this->countConstantNodes($node, $methodName, $className);

        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $functionName = array_pop($this->currentFunctionName);
                if (null === $functionName) {
                    break;
                }

                $this->writer->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'path' => $this->path,
                        'name' => $functionName,
                    ],
                    [
                        MetricKey::SUPERGLOBALS => $this->superglobalsFunction[$functionName] ?? [],
                        MetricKey::VARIABLES => $this->functionVariableMap[$functionName] ?? [],
                        MetricKey::CONSTANTS => $this->functionConstantMap[$functionName] ?? [],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $methodName = array_pop($this->currentMethodName);
                if (false === $className || null === $methodName) {
                    break;
                }

                $this->writer->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    [
                        'path' => $className,
                        'name' => $methodName,
                    ],
                    [
                        MetricKey::SUPERGLOBALS => $this->superglobalsFunction[$methodName] ?? [],
                        MetricKey::VARIABLES => $this->functionVariableMap[$methodName] ?? [],
                        MetricKey::CONSTANTS => $this->functionConstantMap[$methodName] ?? [],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $this->writer->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'path' => $this->path,
                        'name' => $className,
                    ],
                    [
                        MetricKey::SUPERGLOBALS => $this->superglobalsClass[$className] ?? [],
                        MetricKey::VARIABLES => $this->classVariableMap[$className] ?? [],
                        MetricKey::CONSTANTS => $this->classConstantMap[$className] ?? [],
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
        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            [
                MetricKey::SUPERGLOBALS => $this->superglobals,
                MetricKey::VARIABLES => $this->variableMap,
                MetricKey::CONSTANTS => $this->constantMap,
            ]
        );

        return null;
    }

    public function countVariableNodes(Node $node, false|string $functionName, false|string $className, bool $countGlobal = false): void
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            if (in_array($node->name, array_keys($this->superglobals))) {
                if (!$countGlobal) {
                    ++$this->superglobals[$node->name];
                }

                if (false !== $functionName) {
                    ++$this->superglobalsFunction[$functionName][$node->name];
                }

                if (false !== $className) {
                    ++$this->superglobalsClass[$className][$node->name];
                }
            } elseif (!in_array($node->name, ['this', 'self'])) {
                if ($countGlobal) {
                    if (!isset($this->variableMap[$node->name])) {
                        $this->variableMap[$node->name] = 0;
                    }
                    ++$this->variableMap[$node->name];
                }

                if (false !== $functionName) {
                    if (!isset($this->functionVariableMap[$functionName][$node->name])) {
                        $this->functionVariableMap[$functionName][$node->name] = 0;
                    }
                    ++$this->functionVariableMap[$functionName][$node->name];
                }

                if (false !== $className) {
                    if (!isset($this->classVariableMap[$className][$node->name])) {
                        $this->classVariableMap[$className][$node->name] = 0;
                    }
                    ++$this->classVariableMap[$className][$node->name];
                }
            }
        }
    }

    public function countConstantNodes(Node $node, false|string $functionName, false|string $className): void
    {
        if ($node instanceof Node\Expr\ConstFetch) {
            $constantName = $node->name->toString();

            if (!in_array(strtolower($constantName), ['true', 'false', 'null']) && !defined($constantName)) {
                if (!isset($this->constantMap[$constantName])) {
                    $this->constantMap[$constantName] = 0;
                }
                ++$this->constantMap[$constantName];

                if (false !== $functionName) {
                    if (!isset($this->functionConstantMap[$functionName][$constantName])) {
                        $this->functionConstantMap[$functionName][$constantName] = 0;
                    }
                    ++$this->functionConstantMap[$functionName][$constantName];
                }

                if (false !== $className) {
                    if (!isset($this->classConstantMap[$className][$constantName])) {
                        $this->classConstantMap[$className][$constantName] = 0;
                    }
                    ++$this->classConstantMap[$className][$constantName];
                }
            }
        }
    }
}
