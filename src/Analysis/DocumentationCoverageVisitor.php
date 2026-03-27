<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class DocumentationCoverageVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $currentClassName = [];

    // File-level counters
    private int $fileDocumented = 0;
    private int $fileTotal = 0;

    // Class-level counters
    /** @var array<string, int> */
    private array $classDocumented = [];
    /** @var array<string, int> */
    private array $classTotal = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClassName = [];
        $this->fileDocumented = 0;
        $this->fileTotal = 0;
        $this->classDocumented = [];
        $this->classTotal = [];

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->classDocumented[$className] = 0;
                $this->classTotal[$className] = 0;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                // Only count public methods for documentation coverage
                if (!$node->isPublic()) {
                    break;
                }

                // Skip magic methods
                $name = (string) $node->name;
                if (str_starts_with($name, '__')) {
                    break;
                }

                $className = end($this->currentClassName);
                if (false === $className) {
                    break;
                }

                ++$this->classTotal[$className];
                ++$this->fileTotal;

                $hasDoc = $this->hasDocBlock($node);
                $paramCoverage = $this->getParamDocCoverage($node);

                if ($hasDoc) {
                    ++$this->classDocumented[$className];
                    ++$this->fileDocumented;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $className, 'name' => $name],
                    [
                        MetricKey::HAS_DOC_BLOCK => $hasDoc,
                        MetricKey::DOC_PARAM_COVERAGE => $paramCoverage,
                    ]
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                ++$this->fileTotal;

                $hasDoc = $this->hasDocBlock($node);
                $paramCoverage = $this->getParamDocCoverage($node);

                if ($hasDoc) {
                    ++$this->fileDocumented;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $functionName],
                    [
                        MetricKey::HAS_DOC_BLOCK => $hasDoc,
                        MetricKey::DOC_PARAM_COVERAGE => $paramCoverage,
                    ]
                );
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
                $className = array_pop($this->currentClassName);
                if (null === $className) {
                    break;
                }

                $coverage = ($this->classTotal[$className] ?? 0) > 0
                    ? (($this->classDocumented[$className] ?? 0) / $this->classTotal[$className]) * 100
                    : 100.0;

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    round($coverage, 2),
                    MetricKey::DOC_COVERAGE
                );
                break;
        }

        return null;
    }

    /**
     * @param array<int, Node> $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        $coverage = $this->fileTotal > 0
            ? ($this->fileDocumented / $this->fileTotal) * 100
            : 100.0;

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            round($coverage, 2),
            MetricKey::DOC_COVERAGE
        );

        return null;
    }

    private function hasDocBlock(Node $node): bool
    {
        return $node->getDocComment() instanceof \PhpParser\Comment\Doc;
    }

    private function getParamDocCoverage(Node\Stmt\ClassMethod|Node\Stmt\Function_ $node): float
    {
        $params = $node->getParams();
        // Skip promoted properties
        $regularParams = array_filter($params, fn (Node\Param $p): bool => 0 === $p->flags || !$node instanceof Node\Stmt\ClassMethod);

        if (0 === count($regularParams)) {
            return 100.0;
        }

        $docComment = $node->getDocComment();
        if (!$docComment instanceof \PhpParser\Comment\Doc) {
            return 0.0;
        }

        $docText = $docComment->getText();
        $documentedParams = 0;

        foreach ($regularParams as $param) {
            $paramVar = $param->var;
            if (!$paramVar instanceof Node\Expr\Variable || !is_string($paramVar->name)) {
                continue;
            }
            $paramName = '$'.$paramVar->name;
            if (str_contains($docText, '@param') && str_contains($docText, $paramName)) {
                ++$documentedParams;
            }
        }

        return round(($documentedParams / count($regularParams)) * 100, 2);
    }
}
