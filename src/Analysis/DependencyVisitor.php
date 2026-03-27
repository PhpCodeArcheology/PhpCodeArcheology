<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Application\Config;

use function PhpCodeArch\getNodeName;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\MethodCallCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class DependencyVisitor implements NodeVisitor, VisitorInterface, ConfigAwareVisitorInterface, PathAwareVisitorInterface
{
    use VisitorTrait;

    /**
     * @var array<int, string>
     */
    private array $currentClassName = [];

    private bool $insideFunction = false;

    private bool $insideMethod = false;

    /** @var array<string, list<string>> */
    private array $classDependencies = [];

    /**
     * Directly used classes (not in extend or implement).
     *
     * @var array<string, list<string>>
     */
    private array $classUses = [];

    /** @var array<string, list<string>> */
    private array $classTraits = [];

    /** @var list<string> */
    private array $functionDependencies = [];

    /** @var array<string, list<string>> */
    private array $methodDependencies = [];

    /** @var list<string> */
    private array $outsideDependencies = [];

    /**
     * @var array<int, string>
     */
    private array $currentMethodName = [];

    /**
     * Cross-class method call edges: [className][methodName][] = ['targetClass' => ..., 'targetMethod' => ...].
     *
     * @var array<string, array<string, list<array{targetClass: string, targetMethod: string}>>>
     */
    private array $methodCallEdges = [];

    private bool $trackMethodCalls = true;

    public function enterNode(Node $node): int|Node|null
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

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
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

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $dependencyCollection = new ClassNameCollection($this->outsideDependencies);

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $dependencyCollection,
            'dependencies'
        );

        return null;
    }

    private function setDependency(mixed $dependency): ?string
    {
        $dependency = $this->checkDependency($dependency);

        if (false === $dependency) {
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

    private function setUses(mixed $dependency): void
    {
        $dependency = $this->checkDependency($dependency);

        if (false === $dependency) {
            return;
        }

        if (0 === count($this->currentClassName)) {
            return;
        }

        $className = end($this->currentClassName);

        if (in_array($dependency, $this->classUses[$className]) || $className === $dependency || in_array($dependency, ['static', 'self', 'stdClass', 'class'])) {
            return;
        }

        $this->classUses[$className][] = $dependency;
    }

    private function setTrait(mixed $dependency): void
    {
        $dependency = $this->checkDependency($dependency);

        if (false === $dependency) {
            return;
        }

        $className = end($this->currentClassName);
        if (false === $className) {
            return;
        }

        if (in_array($dependency, $this->classTraits[$className])) {
            return;
        }

        $this->classTraits[$className][] = $dependency;
    }

    private function checkDependency(mixed $dependency): string|false
    {
        $dependency = getNodeName($dependency);

        if (!$dependency) {
            return false;
        }

        $dependencyLowercase = strtolower($dependency);

        if ('self' === $dependencyLowercase || 'parent' === $dependencyLowercase) {
            return false;
        }

        return $dependency;
    }

    private function createFunctionDependencies(Node\Stmt\Function_|Node\Stmt\ClassMethod $stmt): void
    {
        foreach ($stmt->getParams() as $parameter) {
            if (!isset($parameter->type)
                || !$parameter->type instanceof Node\Name\FullyQualified) {
                continue;
            }

            $this->setDependency($parameter->type);
        }

        if (isset($stmt->returnType)
            && $stmt->returnType instanceof Node\Name\FullyQualified) {
            $this->setDependency($stmt->returnType);
        }
    }

    public function afterSetPath(string $path): void
    {
        $this->outsideDependencies = [];
    }

    private function setupClassMetrics(Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node): void
    {
        $className = ClassName::ofNode($node)->__toString();

        $this->classDependencies[$className] = [];
        $this->classUses[$className] = [];
        $this->classTraits[$className] = [];
        $this->currentClassName[] = $className;
    }

    private function setupFunctionMetrics(): void
    {
        $this->insideFunction = true;
        $this->functionDependencies = [];
    }

    private function setupMethodMetrics(): void
    {
        $className = end($this->currentClassName);
        if (false === $className) {
            return;
        }
        $this->insideMethod = true;
        $this->methodDependencies[$className] = [];
    }

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
        if (null === $className) {
            return;
        }

        $collections = [
            'dependencies' => new ClassNameCollection($this->classDependencies[$className] ?? []),
            'usedClasses' => new ClassNameCollection($this->classUses[$className] ?? []),
            'traits' => new TraitNameCollection($this->classTraits[$className] ?? []),
            'interfaces' => new InterfaceNameCollection($interfaces),
            'extends' => new ClassNameCollection($extends),
        ];

        foreach ($collections as $collectionKey => $collection) {
            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::ClassCollection,
                [
                    'path' => $this->path,
                    'name' => $className,
                ],
                $collection,
                $collectionKey
            );
        }
    }

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

    private function saveMethodDependencies(Node\Stmt\ClassMethod $node): void
    {
        $className = end($this->currentClassName);
        if (false === $className) {
            $this->insideMethod = false;

            return;
        }
        $methodName = (string) $node->name;

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::MethodCollection,
            [
                'path' => $className,
                'name' => $methodName,
            ],
            new ClassNameCollection($this->methodDependencies[$className] ?? []),
            'dependencies'
        );

        $this->insideMethod = false;
    }

    /**
     * Unused here.
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClassName = [];
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

        return null;
    }

    public function injectConfig(Config $config): void
    {
        $graphConfig = $config->get('graph');
        if (is_array($graphConfig)) {
            $methodCalls = $graphConfig['methodCalls'] ?? true;
            $this->trackMethodCalls = is_bool($methodCalls) ? $methodCalls : true;
        }
    }

    private function recordMethodCall(string $targetClass, string $targetMethod): void
    {
        if (!$this->trackMethodCalls || !$this->insideMethod) {
            return;
        }

        $className = end($this->currentClassName);
        $methodName = end($this->currentMethodName);

        if (false === $className || false === $methodName) {
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
        if ('self' === $targetClassLower || 'static' === $targetClassLower || 'parent' === $targetClassLower) {
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
        if (false === $className) {
            return;
        }
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
