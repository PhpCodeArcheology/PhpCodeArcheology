<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use function Marcus\PhpLegacyAnalyzer\getNodeName;

class DependencyVisitor implements NodeVisitor
{
    use VisitorTrait;

    private string $uses = '';

    private bool $insideClass = false;

    private $classDependencies = [];

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
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_) {

            $this->insideClass = true;
            $this->classDependencies = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_) {

            $this->insideClass = false;

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

                foreach ($stmt->getParams() as $parameter) {
                    if (! isset($parameter->type)
                        || ! $parameter->type instanceof Node\Name\FullyQualified) {
                        continue;
                    }

                    $this->setDependency($parameter->type);
                }

                if (isset($stmt->returnType)
                    && $stmt->returnType instanceof Node\Name\FullyQualified) {
                    echo "fromReturn".PHP_EOL;
                    $this->setDependency($stmt->returnType);
                }
            }

            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $classMetrics = $this->metrics->get($classId);
            $classMetrics->set('dependencies', $this->classDependencies);
        }

        if ($this->insideClass) {
            switch (true) {
                case $node instanceof Node\Expr\New_:
                case $node instanceof Node\Expr\StaticCall:
                    $this->setDependency($node);
                    break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // TODO: Implement afterTraverse() method.
    }

    private function setDependency($dependency): void
    {
        $dependency = getNodeName($dependency);

        $dependencyLowercase = strtolower((string) $dependency);

        if ($dependencyLowercase === 'self' || $dependencyLowercase === 'parent') {
            return;
        }

        $this->classDependencies[] = (string) $dependency;
    }
}