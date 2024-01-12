<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class GlobalsVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

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
     * @var MetricsInterface[]
     */
    private array $classMetrics = [];

    /**
     * @var MetricsInterface[]
     */
    private array $functionMetrics = [];

    private array $constantsDefined = [];

    private array $constantsUsed = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->superglobals = self::GLOBALS;
        $this->variableMap = [];
        $this->classMetrics = [];
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {
            $functionName = strval($node->namespacedName ?? $node->name);

            $this->superglobalsFunction[$functionName] = self::GLOBALS;
            $this->functionVariableMap[$functionName] = [];
            $this->functionConstantMap[$functionName] = [];

            if ($node instanceof Node\Stmt\Function_) {
                $this->functionMetrics[] = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                    $this->metrics,
                    $functionName,
                    $this->path
                );
            }
            else {
                $classMetrics = end($this->classMetrics);
                $methods = $classMetrics->get('methods');

                $this->functionMetrics[] = FunctionMetricsFactory::createFromMethodsByNameAndClassMetrics(
                    $methods,
                    $functionName,
                    $classMetrics
                );
            }
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                $this->metrics,
                $node,
                $this->path
            );

            $this->superglobalsClass[$classMetrics->getName()] = self::GLOBALS;
            $this->classVariableMap[$classMetrics->getName()] = [];
            $this->classConstantMap[$classMetrics->getName()] = [];

            $this->classMetrics[] = $classMetrics;
        }

        // TODO: Extract this to a constant visitor or integrate it here
        /*
        if ($node instanceof Node\Expr\FuncCall
            && $node->name instanceof Node\Name
            && $node->name->toString() === 'define') {

            $constantName = $node->args[0]->value->value;

            if ($constantName !== null) {
                if (! isset($this->constantsDefined[$constantName])) {
                    $this->constantsDefined[$constantName] = 0;
                }
                ++ $this->constantsDefined[$constantName];
            }
        }
        */
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        $classMetrics = end($this->classMetrics);
        $className = null;
        if ($classMetrics) {
            $className = $classMetrics->getName();
        }

        $functionMetrics = end($this->functionMetrics);
        $functionName = null;
        if ($functionMetrics) {
            $functionName = $functionMetrics->getName();
        }

        if ($node instanceof Node\Expr\Variable
            && is_string($node->name)) {

            if (in_array($node->name, array_keys($this->superglobals))) {
                ++ $this->superglobals[$node->name];

                if (count($this->functionMetrics) > 0) {
                    ++ $this->superglobalsFunction[$functionName][$node->name];
                }

                if (count($this->classMetrics) > 0) {
                    ++ $this->superglobalsClass[$className][$node->name];
                }
            }
            elseif (! in_array($node->name, ['this', 'self'])) {
                if (! isset($this->variableMap[$node->name])) {
                    $this->variableMap[$node->name] = 0;
                }
                ++ $this->variableMap[$node->name];

                if (count($this->functionMetrics) > 0) {
                    if (! isset($this->functionVariableMap[$functionName][$node->name])) {
                        $this->functionVariableMap[$functionName][$node->name] = 0;
                    }
                    ++ $this->functionVariableMap[$functionName][$node->name];
                }

                if (count($this->classMetrics) > 0) {
                    if (! isset($this->classVariableMap[$className][$node->name])) {
                        $this->classVariableMap[$className][$node->name] = 0;
                    }
                    ++ $this->classVariableMap[$className][$node->name];
                }
            }
        }

        if ($node instanceof Node\Expr\ConstFetch) {
            $constantName = $node->name->toString();

            if (! in_array(strtolower($constantName), ['true', 'false', 'null']) && ! defined($constantName)) {
                if (! isset($this->constantMap[$constantName])) {
                    $this->constantMap[$constantName] = 0;
                }
                ++ $this->constantMap[$constantName];

                if (count($this->functionMetrics) > 0) {
                    if (! isset($this->functionConstantMap[$functionName][$constantName])) {
                        $this->functionConstantMap[$functionName][$constantName] = 0;
                    }
                    ++ $this->functionConstantMap[$functionName][$constantName];
                }

                if (count($this->classMetrics) > 0) {
                    if (! isset($this->classConstantMap[$className][$constantName])) {
                        $this->classConstantMap[$className][$constantName] = 0;
                    }
                    ++ $this->classConstantMap[$className][$constantName];
                }
            }
        }


        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {

            if ($node instanceof Node\Stmt\Function_) {
                $functionMetrics = array_pop($this->functionMetrics);
                $functionName = $functionMetrics->getName();

                $this->setMetricValues($functionMetrics, [
                    'superglobals' => $this->superglobalsFunction[$functionName],
                    'variables' => $this->functionVariableMap[$functionName],
                    'constants' => $this->functionConstantMap[$functionName],
                ]);

                $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);
            }
            else {
                $classMetrics = end($this->classMetrics);

                array_pop($this->functionMetrics);

                $methods = $classMetrics->get('methods');

                $methodMetrics = FunctionMetricsFactory::createFromMethodsByNameAndClassMetrics(
                    $methods,
                    $node->name,
                    $classMetrics
                );

                $methodName = $methodMetrics->getName();

                $this->setMetricValues($methodMetrics, [
                    'superglobals' => $this->superglobalsFunction[$methodName],
                    'variables' => $this->functionVariableMap[$methodName],
                    'constants' => $this->functionConstantMap[$methodName],
                ]);

                $methods[(string) $methodMetrics->getIdentifier()] = $methodMetrics;

                $classMetrics->set('methods', $methods);
                $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
            }
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $classMetrics = array_pop($this->classMetrics);
            $className = $classMetrics->getName();

            $this->setMetricValues($classMetrics, [
                'superglobals' => $this->superglobalsClass[$className],
                'variables' => $this->classVariableMap[$className],
                'constants' => $this->classConstantMap[$className],
            ]);

            $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);

        $this->setMetricValues($fileMetrics, [
            'superglobals' => $this->superglobals,
            'variables' => $this->variableMap,
            'constants' => $this->constantMap,
        ]);

        $this->metrics->set($fileId, $fileMetrics);
    }
}
