<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function Marcus\PhpLegacyAnalyzer\getNodeName;

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

            $className = (string) ClassName::ofNode($node);

            $this->classDependencies[$className] = [];

            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, $this->path);
            $this->currentClassMetrics[] = $this->metrics->get($classId);
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
            $currentClassMetrics->set('interfaces', $interfaces);
            $currentClassMetrics->set('extends', $extends);

            $this->metrics->set((string) $currentClassMetrics->getIdentifier(), $currentClassMetrics);
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->getFunctionDependencies($node);

            $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $functionMetrics = $this->metrics->get($functionId);
            $functionMetrics->set('dependencies', $this->functionDependencies);

            $this->metrics->set($functionId, $functionMetrics);

            $this->insideFunction = false;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->getFunctionDependencies($node);

            $currentClassMetrics = end($this->currentClassMetrics);
            $className = $currentClassMetrics->getName();

            $methods = $currentClassMetrics->get('methods');
            $classId = (string) $currentClassMetrics->getIdentifier();
            $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->name, $classId);
            $methodMetric = $methods[$methodId];

            $methodMetric->set('dependencies', $this->methodDependencies[$className]);
            $methods[$methodId] = $methodMetric;
            $currentClassMetrics->set('methods', $methods);

            $this->insideMethod = false;
        }

        switch (true) {
            case $node instanceof Node\Expr\New_:
            case $node instanceof Node\Expr\StaticCall:
                $this->setDependency($node);
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
        $dependency = getNodeName($dependency);

        if (! $dependency) {
            return null;
        }

        $dependencyLowercase = strtolower((string) $dependency);

        if ($dependencyLowercase === 'self' || $dependencyLowercase === 'parent') {
            return null;
        }

        if (count($this->currentClassMetrics) > 0) {
            $currentClassMetrics = end($this->currentClassMetrics);
            $className = $currentClassMetrics->getName();

            if (in_array((string) $dependency, $this->classDependencies[$className])) {
                return null;
            }

            $this->classDependencies[$className][] = (string) $dependency;

            if ($this->insideMethod) {
                $this->methodDependencies[$className][] = (string) $dependency;
            }
        }
        elseif ($this->insideFunction) {
            if (in_array((string) $dependency, $this->functionDependencies)) {
                return null;
            }

            $this->functionDependencies[] = (string) $dependency;
        }
        else {
            if (in_array((string) $dependency, $this->outsideDependencies)) {
                return null;
            }

            $this->outsideDependencies[] = (string) $dependency;
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
