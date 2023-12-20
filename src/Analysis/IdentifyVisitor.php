<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class IdentifyVisitor implements NodeVisitor
{
    use VisitorTrait;

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
        $metrics = null;

        if ($node instanceof Node\Stmt\Function_) {
            $metrics = new FunctionMetrics($this->path, (string) $node->namespacedName);
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_) {

            $metrics = new ClassMetrics($this->path, (string) $node->namespacedName);
            $metrics->set('interface', false);
            $metrics->set('trait', false);
            $metrics->set('abstract', false);
            $metrics->set('final', false);

            if (method_exists($node, 'isFinal') && $node->isFinal()) {
                $metrics->set('final', true);
            }

            if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
                $metrics->set('abstract', true);
            }

            if ($node instanceof Node\Stmt\Interface_) {
                $metrics->set('interface', true);
                $metrics->set('abstract', true);
            }

            if ($node instanceof Node\Stmt\Trait_) {
                $metrics->set('trait', true);
            }

            $classMethods = [];
            $privateCount = 0;
            $publicCount = 0;
            $staticCount = 0;
            foreach ($node->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassMethod) {
                    continue;
                }

                $method = new FunctionMetrics(path: '', name: (string) $stmt->name);

                if ($stmt->isPrivate() || $stmt->isProtected()) {
                    $method->set('public', false);
                    $method->set('private', true);

                    ++ $privateCount;
                }

                if ($stmt->isPublic()) {
                    $method->set('public', true);
                    $method->set('private', false);

                    ++ $publicCount;
                }

                $method->set('static', false);
                if ($stmt->isStatic()) {
                    $method->set('static', true);

                    ++ $staticCount;
                }

                $classMethods[(string) $method->getIdentifier()] = $method;
            }

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
    public function leaveNode(Node $node)
    {
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // TODO: Implement afterTraverse() method.
    }
}