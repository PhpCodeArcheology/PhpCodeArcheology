<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetricsFactory;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function PhpCodeArch\getNodeName;

class DependencyVisitor implements NodeVisitor
{
    use VisitorTrait;

    private string $uses = '';

    private bool $insideClass = false;

    private bool $insideFunction = false;

    private bool $insideMethod = false;

    /**
     * @var MetricsInterface[]
     */
    private array $currentClassMetrics = [];

    private array $classDependencies = [];

    /**
     * Directly used classes (not in extend or implement)
     * @var array
     */
    private array $classUses = [];

    private array $classTraits = [];

    private array $functionDependencies = [];

    private array $methodDependencies = [];

    private array $outsideDependencies = [];

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        // TODO: Implement beforeTraverse() method.
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

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
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->insideFunction = true;
            $this->functionDependencies = [];
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $currentClassMetrics = end($this->currentClassMetrics);
            $className = $currentClassMetrics->getName();

            $this->insideMethod = true;
            $this->methodDependencies[$className] = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {

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
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->getFunctionDependencies($node);

            $functionMetrics = FunctionMetricsFactory::createFromMetricsByNameAndPath(
                $this->metrics,
                $node->namespacedName,
                $this->path
            );
            $functionMetrics->set('dependencies', $this->functionDependencies);

            $this->metrics->set((string) $functionMetrics->getIdentifier(), $functionMetrics);

            $this->insideFunction = false;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->getFunctionDependencies($node);

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

        switch (true) {
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
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $fileMetrics = $this->metrics->get($fileId);

        $fileMetrics->set('dependencies', $this->outsideDependencies);
        $this->metrics->set($fileId, $fileMetrics);
    }

    private function setDependency(mixed $dependency): ?string
    {
        $dependency = $this->checkDependency($dependency);

        if ($dependency === false) {
            return null;
        }

        if (count($this->currentClassMetrics) > 0) {
            $currentClassMetrics = end($this->currentClassMetrics);
            $className = $currentClassMetrics->getName();

            if (in_array($dependency, $this->classDependencies[$className])) {
                return null;
            }

            $this->classDependencies[$className][] = $dependency;

            if ($this->insideMethod) {
                $this->methodDependencies[$className][] = $dependency;
            }
        }
        elseif ($this->insideFunction) {
            if (in_array($dependency, $this->functionDependencies)) {
                return null;
            }

            $this->functionDependencies[] = $dependency;
        }
        else {
            if (in_array($dependency, $this->outsideDependencies)) {
                return null;
            }

            $this->outsideDependencies[] = $dependency;
        }

        return $dependency;
    }

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

    private function getFunctionDependencies(Node\Stmt\Function_|Node\Stmt\ClassMethod $stmt): void
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

    private function afterSetPath(): void
    {
        $this->outsideDependencies = [];
    }
}
