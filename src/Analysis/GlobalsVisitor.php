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

    private bool $inFunction = false;

    private bool $inClass = false;

    private MetricsInterface $classMetrics;

    private array $constantsDefined = [];

    private array $constantsUsed = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->superglobals = self::GLOBALS;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {
            $this->superglobalsFunction = self::GLOBALS;
            $this->inFunction = true;
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_) {
            $this->superglobalsClass = self::GLOBALS;
            $this->inClass = true;

            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $this->classMetrics = $this->metrics->get($classId);
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

        if ($node instanceof Node\Expr\ConstFetch) {
            $constantName = $node->name->toString();

            if (!in_array(strtolower($constantName), ['true', 'false', 'null'])) {
                if (! isset($this->constantsUsed[$constantName])) {
                    $this->constantsUsed[$constantName] = 0;
                }
                ++ $this->constantsUsed[$constantName];
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

                if ($this->inFunction && ! $this->inClass) {
                    ++ $this->superglobalsFunction[$node->name];
                }

                if ($this->inClass) {
                    ++ $this->superglobalsClass[$node->name];

                    if ($this->inFunction) {
                        ++ $this->superglobalsFunction[$node->name];
                    }
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
                $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);
            }
            else {
                $methods = $this->classMetrics->get('methods');
                $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, '');
                $methodMetrics = $methods[$methodId];
                $methodMetrics->set('superglobals', $this->superglobalsFunction);
                $methods[$methodId] = $methodMetrics;
                $this->classMetrics->set('methods', $methods);
                $this->metrics->set((string) $this->classMetrics->getIdentifier(), $this->classMetrics);
            }
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_) {
            $this->inClass = false;

            $this->classMetrics->set('superglobals', $this->superglobalsClass);
            $this->metrics->set((string) $this->classMetrics->getIdentifier(), $this->classMetrics);
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
    }
}
