<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\MethodCallCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function PhpCodeArch\getNodeName;

class DependencyVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * @var string[]
     */
    private array $currentClassName = [];

    /**
     * @var bool
     */
    private bool $insideFunction = false;

    /**
     * @var bool
     */
    private bool $insideMethod = false;

    /**
     * @var ClassMetricsCollection[]
     */
    private array $currentClassMetrics = [];

    /**
     * @var array
     */
    private array $classDependencies = [];

    /**
     * Directly used classes (not in extend or implement)
     * @var array
     */
    private array $classUses = [];


    /**
     * @var array
     */
    private array $classTraits = [];

    /**
     * @var array
     */
    private array $functionDependencies = [];

    /**
     * @var array
     */
    private array $methodDependencies = [];

    /**
     * @var array
     */
    private array $outsideDependencies = [];

    /**
     * @var string[]
     */
    private array $currentMethodName = [];

    /**
     * Cross-class method call edges: [className][methodName][] = ['targetClass' => ..., 'targetMethod' => ...]
     * @var array
     */
    private array $methodCallEdges = [];

    private bool $trackMethodCalls = true;

    /**
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $this->setupFunctionMetrics();
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->setupMethodMetrics();
                $this->currentMethodName[] = (string) $node->name;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->setupClassMetrics($node);
                break;
        }
    }

    /**
     * @param Node $node
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->createClassDependencies($node);
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->createFunctionDependencies($node);
                $this->saveFunctionDependencies($node);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->createFunctionDependencies($node);
                $this->saveMethodDependencies($node);
                $this->saveMethodCallEdges($node);
                array_pop($this->currentMethodName);
                break;

            case $node instanceof Node\Expr\New_:
                $this->setDependency($node);
                $this->setUses($node);
                $this->recordMethodCallFromNew($node);
                break;

            case $node instanceof Node\Expr\StaticCall:
                $this->setDependency($node);
                $this->setUses($node);
                $this->recordMethodCallFromStaticCall($node);
                break;

            case $node instanceof Node\Expr\ClassConstFetch:
                $this->setDependency($node->class);
                $this->setUses($node->class);
                break;

            case $node instanceof Node\Stmt\TraitUse:
                foreach ($node->traits as $trait) {
                    $this->setDependency($trait);
                    $this->setTrait($trait);
                }
                break;
        }
    }

    /**
     * @param array $nodes
     * @return void
     */
    public function afterTraverse(array $nodes): void
    {
        $dependencyCollection = new ClassNameCollection($this->outsideDependencies);

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $dependencyCollection,
            'dependencies'
        );
    }

    /**
     * @param mixed $dependency
     * @return string|null
     */
    private function setDependency(mixed $dependency): ?string
    {
        $dependency = $this->checkDependency($dependency);

        if ($dependency === false) {
            return null;
        }

        switch (true) {
            case count($this->currentClassName) > 0:
                $className = end($this->currentClassName);

                if (in_array($dependency, $this->classDependencies[$className])) {
                    return null;
                }

                $this->classDependencies[$className][] = $dependency;

                if ($this->insideMethod) {
                    $this->methodDependencies[$className][] = $dependency;
                }
                break;

            case $this->insideFunction:
                if (in_array($dependency, $this->functionDependencies)) {
                    return null;
                }

                $this->functionDependencies[] = $dependency;
                break;

            default:
                if (in_array($dependency, $this->outsideDependencies)) {
                    return null;
                }

                $this->outsideDependencies[] = $dependency;
                break;
        }


        return $dependency;
    }

    /**
     * @param mixed $dependency
     * @return void
     */
    private function setUses(mixed $dependency): void
    {
        $dependency = $this->checkDependency($dependency);

        if ($dependency === false) {
            return;
        }

        if (count($this->currentClassName) === 0) {
            return;
        }

        $className = end($this->currentClassName);

        if (in_array($dependency, $this->classUses[$className]) || $className === $dependency || in_array($dependency, ['static', 'self', 'stdClass', 'class'])) {
            return;
        }

        $this->classUses[$className][] = $dependency;
    }

    /**
     * @param mixed $dependency
     * @return void
     */
    private function setTrait(mixed $dependency): void
    {
        $dependency = $this->checkDependency($dependency);

        if ($dependency === false) {
            return;
        }

        $className = end($this->currentClassName);

        if (in_array($dependency, $this->classTraits[$className])) {
            return;
        }

        $this->classTraits[$className][] = $dependency;
    }

    /**
     * @param mixed $dependency
     * @return string|false
     */
    private function checkDependency(mixed $dependency): string|false
    {
        $dependency = getNodeName($dependency);

        if (! $dependency) {
            return false;
        }

        $dependencyLowercase = strtolower((string) $dependency);

        if ($dependencyLowercase === 'self' || $dependencyLowercase === 'parent') {
            return false;
        }

        return $dependency;
    }

    /**
     * @param Node\Stmt\Function_|Node\Stmt\ClassMethod $stmt
     * @return void
     */
    private function createFunctionDependencies(Node\Stmt\Function_|Node\Stmt\ClassMethod $stmt): void
    {

        foreach ($stmt->getParams() as $parameter) {
            if (! isset($parameter->type)
                || ! $parameter->type instanceof Node\Name\FullyQualified) {
                continue;
            }

            $this->setDependency($parameter->type);
        }

        if (isset($stmt->returnType)
            && $stmt->returnType instanceof Node\Name\FullyQualified) {

            $this->setDependency($stmt->returnType);
        }
    }

    /**
     * @return void
     */
    private function afterSetPath(): void
    {
        $this->outsideDependencies = [];
    }

    /**
     * @param Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node
     * @return void
     */
    private function setupClassMetrics(Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node): void
    {
        $className = ClassName::ofNode($node)->__toString();

        $this->classDependencies[$className] = [];
        $this->classUses[$className] = [];
        $this->classTraits[$className] = [];
        $this->currentClassName[] = $className;
    }

    /**
     * @return void
     */
    private function setupFunctionMetrics(): void
    {
        $this->insideFunction = true;
        $this->functionDependencies = [];
    }

    /**
     * @return void
     */
    private function setupMethodMetrics(): void
    {
        $className = end($this->currentClassName);
        $this->insideMethod = true;
        $this->methodDependencies[$className] = [];
    }

    /**
     * @param Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node
     * @return void
     */
    private function createClassDependencies(Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node): void
    {
        $extends = [];
        $interfaces = [];

        if (isset($node->extends)) {
            $extends = is_array($node->extends) ? $node->extends : [$node->extends];

            foreach ($extends as $id => $class) {
                $className = $this->setDependency($class);

                $extends[$id] = $className;
            }
        }

        if (isset($node->implements)) {
            $interfaces = is_array($node->implements) ? $node->implements : [$node->implements];

            foreach ($interfaces as $id => $interface) {
                $interfaces[$id] = $this->setDependency($interface);
            }
        }

        $className = array_pop($this->currentClassName);

        $collections = [
            'dependencies' => new ClassNameCollection($this->classDependencies[$className]),
            'usedClasses' => new ClassNameCollection($this->classUses[$className]),
            'traits' => new TraitNameCollection($this->classTraits[$className]),
            'interfaces' => new InterfaceNameCollection($interfaces),
            'extends' => new ClassNameCollection($extends),
        ];

        foreach ($collections as $collectionKey => $collection) {
            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::ClassCollection,
                [
                    'path' => $this->path,
                    'name' => $className
                ],
                $collection,
                $collectionKey
            );
        }
    }

    /**
     * @param Node\Stmt\Function_ $node
     * @return void
     */
    private function saveFunctionDependencies(Node\Stmt\Function_ $node): void
    {
        $functionName = (string) $node->namespacedName;

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::FunctionCollection,
            [
                'path' => $this->path,
                'name' => $functionName,
            ],
            new ClassNameCollection($this->functionDependencies),
            'dependencies'
        );

        $this->insideFunction = false;
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     * @return void
     */
    private function saveMethodDependencies(Node\Stmt\ClassMethod $node): void
    {
        $className = end($this->currentClassName);
        $methodName = (string) $node->name;

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::MethodCollection,
            [
                'path' => $className,
                'name' => $methodName,
            ],
            new ClassNameCollection($this->methodDependencies[$className]),
            'dependencies'
        );

        $this->insideMethod = false;
    }

    /**
     * Unused here
     *
     * @param array $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->currentClassMetrics = [];
        $this->classDependencies = [];
        $this->classUses = [];
        $this->classTraits = [];
        $this->functionDependencies = [];
        $this->methodDependencies = [];
        $this->outsideDependencies = [];
        $this->methodCallEdges = [];
        $this->currentMethodName = [];
        $this->insideFunction = false;
        $this->insideMethod = false;
    }

    public function injectConfig(Config $config): void
    {
        $graphConfig = $config->get('graph') ?? [];
        $this->trackMethodCalls = $graphConfig['methodCalls'] ?? true;
    }

    private function recordMethodCall(string $targetClass, string $targetMethod): void
    {
        if (!$this->trackMethodCalls || !$this->insideMethod) {
            return;
        }

        $className = end($this->currentClassName);
        $methodName = end($this->currentMethodName);

        if (!$className || !$methodName) {
            return;
        }

        if ($targetClass === $className) {
            return;
        }

        $this->methodCallEdges[$className][$methodName][] = [
            'targetClass' => $targetClass,
            'targetMethod' => $targetMethod,
        ];
    }

    private function recordMethodCallFromStaticCall(Node\Expr\StaticCall $node): void
    {
        $targetClass = getNodeName($node->class);

        if (!$targetClass || !$node->name instanceof Node\Identifier) {
            return;
        }

        $targetClassLower = strtolower($targetClass);
        if ($targetClassLower === 'self' || $targetClassLower === 'static' || $targetClassLower === 'parent') {
            return;
        }

        $this->recordMethodCall($targetClass, (string) $node->name);
    }

    private function recordMethodCallFromNew(Node\Expr\New_ $node): void
    {
        $targetClass = getNodeName($node->class);

        if (!$targetClass) {
            return;
        }

        $this->recordMethodCall($targetClass, '__construct');
    }

    private function saveMethodCallEdges(Node\Stmt\ClassMethod $node): void
    {
        if (!$this->trackMethodCalls) {
            return;
        }

        $className = end($this->currentClassName);
        $methodName = (string) $node->name;
        $calls = $this->methodCallEdges[$className][$methodName] ?? [];

        if (empty($calls)) {
            return;
        }

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::MethodCollection,
            [
                'path' => $className,
                'name' => $methodName,
            ],
            new MethodCallCollection($calls),
            'methodCalls'
        );
    }
}
