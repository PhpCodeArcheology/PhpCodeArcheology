<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function PhpCodeArch\getNodeName;

class IdentifyVisitor implements NodeVisitor
{
    use VisitorTrait;

    /**
     * @var string[]
     */
    private array $classes = [];

    /**
     * @var string[]
     */
    private array $interfaces = [];

    /**
     * @var string[]
     */
    private array $traits = [];

    /**
     * @var string[]
     */
    private array $enums = [];

    /**
     * @var string[]
     */
    private array $functions = [];

    /**
     * @var string[]
     */
    private array $methods = [];

    /**
     * @var bool
     */
    private bool $inFunction = false;

    /**
     * @var bool
     */
    private bool $inClass = false;

    /**
     * @var int[]
     */
    private array $outputCount = [
        'overall' => 0,
        'file' => 0,
        'classes' => 0,
        'functions' => 0,
        'methods' => 0,
    ];

    /**
     * @param Node[] $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->projectMetrics = $this->metrics->get('project');
        $this->outputCount['file'] = 0;
    }

    /**
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node): void
    {
        $metrics = null;

        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $metrics = $this->setFunctionMetrics($node);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->inFunction = true;
                $this->outputCount['methods'] = 0;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $metrics = $this->setClassMetrics($node);
                break;
        }

        if ($metrics instanceof MetricsInterface) {
            $this->metrics->push($metrics);
        }
    }

    /**
     * Mainly used for setting and saving output counts
     *
     * @param Node $node
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Echo_:
            case $node instanceof Node\Expr\Print_:
                // Count numbers of output statements

                $this->countOutput();
                break;

            case $node instanceof Node\Expr\FuncCall:
                // Count printf as output statement
                $functionName = $node->name instanceof Node\Name ? $node->name->toString() : null;
                if ($functionName === 'printf') {
                    $this->countOutput();
                }
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->inFunction = false;

                $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                    $this->metrics,
                    $node->namespacedName,
                    $this->path
                );

                $functionMetrics->set('outputCount', $this->outputCount['functions']);
                $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->inFunction = false;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->inClass = false;

                $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
                    $this->metrics,
                    $node,
                    $this->path
                );
                $classMetrics->set('outputCount', $this->outputCount['classes']);
                $this->metrics->set((string) $classMetrics->getIdentifier(), $classMetrics);
                break;
        }
    }

    /**
     * Save metrics
     *
     * @param Node[] $nodes
     * @return void
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

    /**
     * Count output statements
     *
     * @return void
     */
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

    /**
     * Set parameters on functions and class methods
     *
     * @param Node\Stmt\Function_|Node\Stmt\ClassMethod $node
     * @param FunctionMetrics $metrics
     * @return void
     */
    private function handleParameters(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $node,
        FunctionMetrics $metrics): void
    {
        $parameters = [];

        foreach ($node->getParams() as $parameter) {
            $type = null;

            if ($parameter->type !== null) {
                switch (true) {
                    case $parameter->type instanceof Node\Name\FullyQualified:
                    case $parameter->type instanceof Node\Name:
                        $type = getNodeName($parameter->type);
                        break;

                    case $parameter->type instanceof Node\Identifier:
                    case $parameter->type instanceof Node\Expr\Variable:
                        $type = $parameter->type->name;
                        break;
                }
            }

            $parameters[] = [
                'name' => '$' . (string) $parameter->var->name,
                'type' => $type,
            ];
        }

        $metrics->set('parameters', $parameters);
    }

    /**
     * @param Node\Stmt\Function_ $node
     * @return FunctionMetrics
     */
    private function setFunctionMetrics(Node\Stmt\Function_ $node): FunctionMetrics
    {
        $metrics = new FunctionMetrics($this->path, (string) $node->namespacedName);
        $metrics->set('singleName', (string) $node->name);
        $namespace = str_replace((string) $node->name, '', (string) $node->namespacedName);
        $namespace = rtrim($namespace, '\\');
        $metrics->set('namespace', $namespace);

        $this->handleParameters($node, $metrics);

        $fnCount = $this->projectMetrics->get('OverallFunctions') + 1;
        $this->projectMetrics->set('OverallFunctions', $fnCount);

        $this->functions[(string) $metrics->getIdentifier()] = $metrics->getName();
        $this->inFunction = true;
        $this->outputCount['functions'] = 0;

        return $metrics;
    }

    /**
     * @param Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node
     * @return ClassMetrics
     */
    private function setClassMetrics(
        Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node): ClassMetrics
    {
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

        $metrics->set('singleName', (string) $node->name);
        $namespace = str_replace((string) $node->name, '', (string) $node->namespacedName);
        $namespace = rtrim($namespace, '\\');
        $metrics->set('namespace', $namespace);

        if (method_exists($node, 'isFinal') && $node->isFinal()) {
            $metrics->set('final', true);
        }

        if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
            $abstractClassCount = $this->projectMetrics->get('OverallAbstractClasses') + 1;
            $this->projectMetrics->set('OverallAbstractClasses', $abstractClassCount);

            $metrics->set('abstract', true);
        }

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
                $classCount = $this->projectMetrics->get('OverallClasses') + 1;
                $this->projectMetrics->set('OverallClasses', $classCount);

                $this->classes[(string) $metrics->getIdentifier()] = $metrics->getName();
                $metrics->set('realClass', true);
                break;

            case $node instanceof Node\Stmt\Enum_:
                $metrics->set('enum', true);
                $this->enums[(string) $metrics->getIdentifier()] = $metrics->getName();
                break;

            case $node instanceof Node\Stmt\Interface_:
                $interfaceCount = $this->projectMetrics->get('OverallInterfaces') + 1;
                $this->projectMetrics->set('OverallInterfaces', $interfaceCount);

                $metrics->set('interface', true);
                $metrics->set('abstract', true);

                $this->interfaces[(string) $metrics->getIdentifier()] = $metrics->getName();
                break;

            case $node instanceof Node\Stmt\Trait_:
                $metrics->set('trait', true);

                $this->traits[(string) $metrics->getIdentifier()] = $metrics->getName();
                break;
        }

        $methodData = [
            'classMethods' => [],
            'privateCount' => 0,
            'publicCount' => 0,
            'staticCount' => 0,
            'overAllMethodsCount' => $this->projectMetrics->get('OverallMethods'),
            'overAllPublicMethodsCount' => $this->projectMetrics->get('OverallPublicMethods'),
            'overAllPrivateMethodsCount' => $this->projectMetrics->get('OverallPrivateMethods'),
            'overAllStaticMethodsCount' => $this->projectMetrics->get('OverallStaticMethods'),
        ];

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            $methodData = $this->handleClassMethod($stmt, $methodData, $metrics);
        }

        $methodData['overAllMethodsCount'] += count($methodData['classMethods']);

        $this->projectMetrics->set('OverallMethods', $methodData['overAllMethodsCount']);
        $this->projectMetrics->set('OverallPublicMethods', $methodData['overAllPublicMethodsCount']);
        $this->projectMetrics->set('OverallPrivateMethods', $methodData['overAllPrivateMethodsCount']);
        $this->projectMetrics->set('OverallStaticMethods', $methodData['overAllStaticMethodsCount']);

        $metrics->set('methods', $methodData['classMethods']);
        $metrics->set('methodCount', count($methodData['classMethods']));
        $metrics->set('privateMethods', $methodData['privateCount']);
        $metrics->set('publicMethods', $methodData['publicCount']);
        $metrics->set('staticMethods', $methodData['staticCount']);

        return $metrics;
    }

    /**
     * @param Node\Stmt\ClassMethod $stmt
     * @param array $methodData
     * @param ClassMetrics $metrics
     * @return array
     */
    private function handleClassMethod(
        Node\Stmt\ClassMethod $stmt,
        array $methodData,
        ClassMetrics $metrics): array
    {
        $method = new FunctionMetrics(path: (string) $metrics->getIdentifier(), name: (string) $stmt->name);
        $method->set('name', $method->getName());

        $this->handleParameters($stmt, $method);

        if (! isset($this->methods[(string) $metrics->getIdentifier()])) {
            $this->methods[(string) $metrics->getIdentifier()] = [];
        }
        $this->methods[(string) $metrics->getIdentifier()][(string) $method->getIdentifier()] = $method->getName();

        $method->set('protected', $stmt->isProtected());

        if ($stmt->isPrivate() || $stmt->isProtected()) {
            $method->set('public', false);
            $method->set('private', true);

            ++ $methodData['privateCount'];
            ++ $methodData['overAllPrivateMethodsCount'];
        }

        if ($stmt->isPublic()) {
            $method->set('public', true);
            $method->set('private', false);

            ++ $methodData['publicCount'];
            ++ $methodData['overAllPublicMethodsCount'];
        }

        $method->set('static', false);
        if ($stmt->isStatic()) {
            $method->set('static', true);

            ++ $methodData['staticCount'];
            ++ $methodData['overAllStaticMethodsCount'];
        }

        $methodData['classMethods'][(string) $method->getIdentifier()] = $method;

        return $methodData;
    }
}
