<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class DocumentationCoverageVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $currentClassName = [];

    // File-level counters
    private int $fileDocumented = 0;
    private int $fileTotal = 0;

    // Class-level counters
    private array $classDocumented = [];
    private array $classTotal = [];

    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->fileDocumented = 0;
        $this->fileTotal = 0;
        $this->classDocumented = [];
        $this->classTotal = [];
    }

    public function enterNode(Node $node): void
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
                if ($className === false) {
                    break;
                }

                $this->classTotal[$className]++;
                $this->fileTotal++;

                $hasDoc = $this->hasDocBlock($node);
                $paramCoverage = $this->getParamDocCoverage($node);

                if ($hasDoc) {
                    $this->classDocumented[$className]++;
                    $this->fileDocumented++;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $className, 'name' => $name],
                    [
                        'hasDocBlock' => $hasDoc,
                        'docParamCoverage' => $paramCoverage,
                    ]
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $functionName = (string) $node->namespacedName;

                $this->fileTotal++;

                $hasDoc = $this->hasDocBlock($node);
                $paramCoverage = $this->getParamDocCoverage($node);

                if ($hasDoc) {
                    $this->fileDocumented++;
                }

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $functionName],
                    [
                        'hasDocBlock' => $hasDoc,
                        'docParamCoverage' => $paramCoverage,
                    ]
                );
                break;
        }
    }

    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);

                $coverage = $this->classTotal[$className] > 0
                    ? ($this->classDocumented[$className] / $this->classTotal[$className]) * 100
                    : 100.0;

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    round($coverage, 2),
                    'docCoverage'
                );
                break;
        }
    }

    public function afterTraverse(array $nodes): void
    {
        $coverage = $this->fileTotal > 0
            ? ($this->fileDocumented / $this->fileTotal) * 100
            : 100.0;

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            round($coverage, 2),
            'docCoverage'
        );
    }

    private function hasDocBlock(Node $node): bool
    {
        return $node->getDocComment() !== null;
    }

    private function getParamDocCoverage(Node\Stmt\ClassMethod|Node\Stmt\Function_ $node): float
    {
        $params = $node->getParams();
        // Skip promoted properties
        $regularParams = array_filter($params, fn($p) => $p->flags === 0 || !$node instanceof Node\Stmt\ClassMethod);

        if (count($regularParams) === 0) {
            return 100.0;
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return 0.0;
        }

        $docText = $docComment->getText();
        $documentedParams = 0;

        foreach ($regularParams as $param) {
            $paramName = '$' . (string) $param->var->name;
            if (str_contains($docText, '@param') && str_contains($docText, $paramName)) {
                $documentedParams++;
            }
        }

        return round(($documentedParams / count($regularParams)) * 100, 2);
    }
}
