<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class GlobalsVisitor implements NodeVisitor
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

    private bool $inFunction = false;

    private bool $inClass = false;

    /**
     * @var MetricsInterface[]
     */
    private array $classMetrics = [];

    private array $constantsDefined = [];

    private array $constantsUsed = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->superglobals = self::GLOBALS;
        $this->variableMap = [];
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {
            $this->superglobalsFunction = self::GLOBALS;
            $this->functionVariableMap = [];
            $this->functionConstantMap = [];
            $this->inFunction = true;
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->superglobalsClass = self::GLOBALS;
            $this->classVariableMap = [];
            $this->classConstantMap = [];
            $this->inClass = true;

            $className = (string) ClassName::ofNode($node);
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, $this->path);
            $this->classMetrics[] = $this->metrics->get($classId);
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
        if ($node instanceof Node\Expr\Variable
            && is_string($node->name)) {

            if (in_array($node->name, array_keys($this->superglobals))) {
                ++ $this->superglobals[$node->name];

                if ($this->inFunction) {
                    ++ $this->superglobalsFunction[$node->name];
                }

                if ($this->inClass) {
                    ++ $this->superglobalsClass[$node->name];
                }
            }
            elseif (! in_array($node->name, ['this', 'self'])) {
                if (! isset($this->variableMap[$node->name])) {
                    $this->variableMap[$node->name] = 0;
                }
                ++ $this->variableMap[$node->name];

                if ($this->inFunction) {
                    if (! isset($this->functionVariableMap[$node->name])) {
                        $this->functionVariableMap[$node->name] = 0;
                    }
                    ++ $this->functionVariableMap[$node->name];
                }

                if ($this->inClass) {
                    if (! isset($this->classVariableMap[$node->name])) {
                        $this->classVariableMap[$node->name] = 0;
                    }
                    ++ $this->classVariableMap[$node->name];
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

                if ($this->inFunction) {
                    if (! isset($this->functionConstantMap[$constantName])) {
                        $this->functionConstantMap[$constantName] = 0;
                    }
                    ++ $this->functionConstantMap[$constantName];
                }

                if ($this->inClass) {
                    if (! isset($this->classConstantMap[$constantName])) {
                        $this->classConstantMap[$constantName] = 0;
                    }
                    ++ $this->classConstantMap[$constantName];
                }
            }
        }


        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {
            $this->inFunction = false;

            if ($node instanceof Node\Stmt\Function_) {
                $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
                $functionMetrics = $this->metrics->get($functionId);
                $functionMetrics->set('superglobals', $this->superglobalsFunction);
                $functionMetrics->set('variables', $this->functionVariableMap);
                $functionMetrics->set('constants', $this->functionConstantMap);
                $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);
            }
            else {
                $classMetrics = end($this->classMetrics);

                $methods = $classMetrics->get('methods');

                $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, (string) $classMetrics->getIdentifier());
                $methodMetrics = $methods[$methodId];
                $methodMetrics->set('superglobals', $this->superglobalsFunction);
                $methodMetrics->set('variables', $this->functionVariableMap);
                $methodMetrics->set('constants', $this->functionVariableMap);
                $methods[$methodId] = $methodMetrics;

                $classMetrics->set('methods', $methods);
                $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
            }
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->inClass = false;

            $classMetrics = array_pop($this->classMetrics);

            $classMetrics->set('superglobals', $this->superglobalsClass);
            $classMetrics->set('variables', $this->classVariableMap);
            $classMetrics->set('constants', $this->classConstantMap);
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

        $fileMetrics->set('superglobals', $this->superglobals);
        $fileMetrics->set('variables', $this->variableMap);
        $fileMetrics->set('constants', $this->constantMap);

        $this->metrics->set($fileId, $fileMetrics);
    }
}
