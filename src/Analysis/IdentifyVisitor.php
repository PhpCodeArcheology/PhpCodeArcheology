<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetricsFactory;
use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetricsFactory;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class IdentifyVisitor implements NodeVisitor
{
    use VisitorTrait;

    private array $classes = [];

    private array $interfaces = [];

    private array $traits = [];

    private array $enums = [];

    private array $functions = [];

    private array $methods = [];

    private bool $inFunction = false;

    private bool $inClass = false;

    private array $outputCount = [
        'overall' => 0,
        'file' => 0,
        'classes' => 0,
        'functions' => 0,
        'methods' => 0,
    ];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->projectMetrics = $this->metrics->get('project');
        $this->outputCount['file'] = 0;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        $metrics = null;

        if ($node instanceof Node\Stmt\Function_) {
            $metrics = new FunctionMetrics($this->path, (string) $node->namespacedName);
            $fnCount = $this->projectMetrics->get('OverallFunctions') + 1;
            $this->projectMetrics->set('OverallFunctions', $fnCount);

            $this->functions[(string) $metrics->getIdentifier()] = $metrics->getName();
            $this->inFunction = true;
            $this->outputCount['functions'] = 0;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->inFunction = true;
            $this->outputCount['methods'] = 0;
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->inClass = true;
            $this->outputCount['classes'] = 0;

            $className = (string) ClassName::ofNode($node);
            $metrics = new ClassMetrics($this->path, $className);
            $metrics->set('interface', false);
            $metrics->set('trait', false);
            $metrics->set('abstract', false);
            $metrics->set('enum', false);
            $metrics->set('final', false);
            $metrics->set('realClass', false);
            $metrics->set('anonymous', str_starts_with($className, 'anonymous@'));

            if (method_exists($node, 'isFinal') && $node->isFinal()) {
                $metrics->set('final', true);
            }

            if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
                $abstractClassCount = $this->projectMetrics->get('OverallAbstractClasses') + 1;
                $this->projectMetrics->set('OverallAbstractClasses', $abstractClassCount);

                $metrics->set('abstract', true);
            }

            if ($node instanceof Node\Stmt\Class_) {
                $classCount = $this->projectMetrics->get('OverallClasses') + 1;
                $this->projectMetrics->set('OverallClasses', $classCount);

                $this->classes[(string) $metrics->getIdentifier()] = $metrics->getName();
                $metrics->set('realClass', true);
            }

            if ($node instanceof Node\Stmt\Enum_) {
                $metrics->set('enum', true);
                $this->enums[(string) $metrics->getIdentifier()] = $metrics->getName();
            }

            if ($node instanceof Node\Stmt\Interface_) {
                $interfaceCount = $this->projectMetrics->get('OverallInterfaces') + 1;
                $this->projectMetrics->set('OverallInterfaces', $interfaceCount);

                $metrics->set('interface', true);
                $metrics->set('abstract', true);

                $this->interfaces[(string) $metrics->getIdentifier()] = $metrics->getName();
            }

            if ($node instanceof Node\Stmt\Trait_) {
                $metrics->set('trait', true);

                $this->traits[(string) $metrics->getIdentifier()] = $metrics->getName();
            }

            $classMethods = [];
            $privateCount = 0;
            $publicCount = 0;
            $staticCount = 0;

            $overAllMethodsCount = $this->projectMetrics->get('OverallMethods');
            $overAllPublicMethodsCount = $this->projectMetrics->get('OverallPublicMethods');
            $overAllPrivateMethodsCount = $this->projectMetrics->get('OverallPrivateMethods');
            $overAllStaticMethodsCount = $this->projectMetrics->get('OverallStaticMethods');

            foreach ($node->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassMethod) {
                    continue;
                }

                $method = new FunctionMetrics(path: (string) $metrics->getIdentifier(), name: (string) $stmt->name);

                if (! isset($this->methods[(string) $metrics->getIdentifier()])) {
                    $this->methods[(string) $metrics->getIdentifier()] = [];
                }
                $this->methods[(string) $metrics->getIdentifier()][(string) $method->getIdentifier()] = $method->getName();

                if ($stmt->isPrivate() || $stmt->isProtected()) {
                    $method->set('public', false);
                    $method->set('private', true);

                    ++ $privateCount;
                    ++ $overAllPrivateMethodsCount;
                }

                if ($stmt->isPublic()) {
                    $method->set('public', true);
                    $method->set('private', false);

                    ++ $publicCount;
                    ++ $overAllPublicMethodsCount;
                }

                $method->set('static', false);
                if ($stmt->isStatic()) {
                    $method->set('static', true);

                    ++ $staticCount;
                    ++ $overAllStaticMethodsCount;
                }

                $classMethods[(string) $method->getIdentifier()] = $method;
            }

            $overAllMethodsCount += count($classMethods);

            $this->projectMetrics->set('OverallMethods', $overAllMethodsCount);
            $this->projectMetrics->set('OverallPublicMethods', $overAllPublicMethodsCount);
            $this->projectMetrics->set('OverallPrivateMethods', $overAllPrivateMethodsCount);
            $this->projectMetrics->set('OverallStaticMethods', $overAllStaticMethodsCount);

            $metrics->set('methods', $classMethods);
            $metrics->set('methodCount', count($classMethods));
            $metrics->set('privateMethods', $privateCount);
            $metrics->set('publicMethods', $publicCount);
            $metrics->set('staticMethods', $staticCount);
        }

        if ($metrics instanceof MetricsInterface) {
            $this->metrics->push($metrics);
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Echo_:
            case $node instanceof Node\Expr\Print_:
                $this->countOutput();
                break;

            case $node instanceof Node\Expr\FuncCall:
                $functionName = $node->name instanceof Node\Name ? $node->name->toString() : null;
                if ($functionName === 'printf') {
                    $this->countOutput();
                }
                break;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $this->inFunction = false;

            $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                $this->metrics,
                $node->namespacedName,
                $this->path
            );

            $functionMetrics->set('outputCount', $this->outputCount['functions']);
            $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->inFunction = false;
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->inClass = false;

            $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                $this->metrics,
                $node,
                $this->path
            );
            $classMetrics->set('outputCount', $this->outputCount['classes']);
            $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $this->metrics->set('classes', $this->classes);
        $this->metrics->set('interfaces', $this->interfaces);
        $this->metrics->set('traits', $this->traits);
        $this->metrics->set('enums', $this->enums);
        $this->metrics->set('functions', $this->functions);
        $this->metrics->set('methods', $this->methods);

        $this->projectMetrics->set('OverallOutputStatements', $this->outputCount['overall']);

        $this->metrics->set('project', $this->projectMetrics);

        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);
        $fileMetrics->set('outputCount', $this->outputCount['file']);
        $this->metrics->set($fileId, $fileMetrics);
    }

    private function countOutput(): void
    {
        ++ $this->outputCount['overall'];
        ++ $this->outputCount['file'];

        if ($this->inClass) {
            ++ $this->outputCount['classes'];

            if ($this->inFunction) {
                ++ $this->outputCount['methods'];
            }
        }
        elseif ($this->inFunction) {
            ++ $this->outputCount['functions'];
        }
    }
}
