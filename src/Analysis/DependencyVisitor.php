<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function PhpCodeArch\getNodeName;

class DependencyVisitor implements NodeVisitor
{
    use VisitorTrait;

    /**
     * @var string
     */
    private string $uses = '';

    /**
     * @var bool
     */
    private bool $insideClass = false;

    /**
     * @var bool
     */
    private bool $insideFunction = false;

    /**
     * @var bool
     */
    private bool $insideMethod = false;

    /**
     * @var ClassMetrics[]
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
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

            $this->setupClassMetrics($node);
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->setupFunctionMetrics();
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->setupMethodMetrics();
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
                break;

            case $node instanceof Node\Expr\New_:
            case $node instanceof Node\Expr\StaticCall:
                $this->setDependency($node);
                $this->setUses($node);
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
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);

        $fileMetrics->set('dependencies', $this->outsideDependencies);
        $this->metrics->set($fileId, $fileMetrics);
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
            case count($this->currentClassMetrics) > 0:
                $currentClassMetrics = end($this->currentClassMetrics);
                $className = $currentClassMetrics->getName();

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

        if (count($this->currentClassMetrics) === 0) {
            return;
        }

        $currentClassMetrics = end($this->currentClassMetrics);
        $className = $currentClassMetrics->getName();

        if (in_array($dependency, $this->classUses[$className])) {
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

        if (count($this->currentClassMetrics) === 0) {
            return;
        }

        $currentClassMetrics = end($this->currentClassMetrics);
        $className = $currentClassMetrics->getName();

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
     * @param Node $node
     * @return void
     */
    private function setupClassMetrics(Node $node): void
    {
        $classMetrics = ClassMetricsFactory::createFromMetricsByNodeAndPath(
            $this->metrics,
            $node,
            $this->path
        );

        $this->classDependencies[$classMetrics->getName()] = [];
        $this->classUses[$classMetrics->getName()] = [];
        $this->classTraits[$classMetrics->getName()] = [];
        $this->currentClassMetrics[] = $classMetrics;
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
        $currentClassMetrics = end($this->currentClassMetrics);
        $className = $currentClassMetrics->getName();

        $this->insideMethod = true;
        $this->methodDependencies[$className] = [];
    }

    /**
     * @param Node $node
     * @return void
     */
    private function createClassDependencies(Node $node): void
    {
        $extends = [];
        $interfaces = [];

        if (isset($node->extends)) {
            $extends = is_array($node->extends) ? $node->extends : [$node->extends];

            foreach ($extends as $id => $class) {
                $className = $this->setDependency($class);

                if (! $className) {
                    continue;
                }

                $extends[$id] = $className;
            }
        }

        if (isset($node->implements)) {
            $interfaces = is_array($node->implements) ? $node->implements : [$node->implements];

            foreach ($interfaces as $id => $interface) {
                $interfaces[$id] = $this->setDependency($interface);
            }
        }

        $currentClassMetrics = array_pop($this->currentClassMetrics);

        $currentClassMetrics->set('dependencies', $this->classDependencies[$currentClassMetrics->getName()]);
        $currentClassMetrics->set('usedClasses', $this->classUses[$currentClassMetrics->getName()]);
        $currentClassMetrics->set('traits', $this->classTraits[$currentClassMetrics->getName()]);
        $currentClassMetrics->set('interfaces', $interfaces);
        $currentClassMetrics->set('extends', $extends);

        $this->metrics->set((string) $currentClassMetrics->getIdentifier(), $currentClassMetrics);
    }

    /**
     * @param Node\Stmt\Function_ $node
     * @return void
     */
    private function saveFunctionDependencies(Node\Stmt\Function_ $node): void
    {
        $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
            $this->metrics,
            $node->namespacedName,
            $this->path
        );
        $functionMetrics->set('dependencies', $this->functionDependencies);

        $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);

        $this->insideFunction = false;
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     * @return void
     */
    private function saveMethodDependencies(Node\Stmt\ClassMethod $node): void
    {
        $currentClassMetrics = end($this->currentClassMetrics);
        $methods = $currentClassMetrics->get('methods');

        $methodMetric = FunctionMetricsFactory::createFromMethodsByNameAndClassMetrics(
            $methods,
            $node->name,
            $currentClassMetrics
        );
        $methodMetric->set('dependencies', $this->methodDependencies[$currentClassMetrics->getName()]);
        $methods[(string) $methodMetric->getIdentifier()] = $methodMetric;
        $currentClassMetrics->set('methods', $methods);

        $this->insideMethod = false;
    }

    /**
     * Unused here
     *
     * @param array $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes)
    {}
}
