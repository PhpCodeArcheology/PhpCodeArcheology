<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function Marcus\PhpLegacyAnalyzer\getNodeName;

class DependencyVisitor implements NodeVisitor
{
    use VisitorTrait;

    private string $uses = '';

    private bool $insideClass = false;

    private bool $insideFunction = false;

    private array $classDependencies = [];

    private array $functionDependencies = [];

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

            $this->insideClass = true;
            $this->classDependencies = [];
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->insideFunction = true;
            $this->functionDependencies = [];
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

            if (isset($node->extends)) {
                $extends = is_array($node->extends) ? $node->extends : [$node->extends];

                foreach ($extends as $class) {
                    $this->setDependency($class);
                }
            }

            if (isset($node->implements)) {
                $implements = is_array($node->implements) ? $node->implements : [$node->implements];

                foreach ($implements as $class) {
                    $this->setDependency($class);
                }
            }

            foreach ($node->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassMethod) {
                    continue;
                }

                $this->getFunctionDependencies($stmt);
            }

            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $classMetrics = $this->metrics->get($classId);
            $classMetrics->set('dependencies', $this->classDependencies);

            $this->insideClass = false;
        }
        elseif ($node instanceof Node\Stmt\Function_) {
            $this->getFunctionDependencies($node);

            $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $functionMetrics = $this->metrics->get($functionId);
            $functionMetrics->set('dependencies', $this->functionDependencies);

            $this->insideFunction = false;
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

    private function setDependency(mixed $dependency): void
    {
        $dependency = getNodeName($dependency);

        $dependencyLowercase = strtolower((string) $dependency);

        if ($dependencyLowercase === 'self' || $dependencyLowercase === 'parent') {
            return;
        }

        if ($this->insideClass) {
            if (in_array((string) $dependency, $this->classDependencies)) {
                return;
            }

            $this->classDependencies[] = (string) $dependency;
            return;
        }
        elseif ($this->insideFunction) {
            if (in_array((string) $dependency, $this->functionDependencies)) {
                return;
            }

            $this->functionDependencies[] = (string) $dependency;
        }
        else {
            if (in_array((string) $dependency, $this->outsideDependencies)) {
                return;
            }

            $this->outsideDependencies[] = (string) $dependency;
        }
    }

    private function getFunctionDependencies(Node\Stmt\ClassMethod|Node\Stmt\Function_ $stmt): void
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
