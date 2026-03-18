<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class TypeCoverageVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private array $currentClassName = [];
    private array $currentFunctionName = [];

    // File-level counters
    private int $fileTypedParams = 0;
    private int $fileTotalParams = 0;
    private int $fileTypedReturns = 0;
    private int $fileTotalReturns = 0;
    private int $fileTypedProperties = 0;
    private int $fileTotalProperties = 0;

    // Class-level counters
    private array $classTypedParams = [];
    private array $classTotalParams = [];
    private array $classTypedReturns = [];
    private array $classTotalReturns = [];
    private array $classTypedProperties = [];
    private array $classTotalProperties = [];

    // Function/Method-level counters
    private array $funcTypedParams = [];
    private array $funcTotalParams = [];
    private array $funcTypedReturns = [];
    private array $funcTotalReturns = [];

    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->fileTypedParams = 0;
        $this->fileTotalParams = 0;
        $this->fileTypedReturns = 0;
        $this->fileTotalReturns = 0;
        $this->fileTypedProperties = 0;
        $this->fileTotalProperties = 0;
        $this->classTypedParams = [];
        $this->classTotalParams = [];
        $this->classTypedReturns = [];
        $this->classTotalReturns = [];
        $this->classTypedProperties = [];
        $this->classTotalProperties = [];
        $this->funcTypedParams = [];
        $this->funcTotalParams = [];
        $this->funcTypedReturns = [];
        $this->funcTotalReturns = [];
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
                $this->classTypedParams[$className] = 0;
                $this->classTotalParams[$className] = 0;
                $this->classTypedReturns[$className] = 0;
                $this->classTotalReturns[$className] = 0;
                $this->classTypedProperties[$className] = 0;
                $this->classTotalProperties[$className] = 0;
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->handleFunctionEnter($node, true);
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->handleFunctionEnter($node, false);
                break;

            case $node instanceof Node\Stmt\Property:
                if (count($this->currentClassName) > 0) {
                    $className = end($this->currentClassName);
                    $propCount = count($node->props);
                    $this->classTotalProperties[$className] += $propCount;
                    $this->fileTotalProperties += $propCount;

                    if ($node->type !== null) {
                        $this->classTypedProperties[$className] += $propCount;
                        $this->fileTypedProperties += $propCount;
                    }
                }
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
                $coverage = $this->calculateCoverage(
                    $this->classTypedParams[$className],
                    $this->classTotalParams[$className],
                    $this->classTypedReturns[$className],
                    $this->classTotalReturns[$className],
                    $this->classTypedProperties[$className],
                    $this->classTotalProperties[$className],
                );

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    [
                        'typeCoverage' => $coverage,
                        'typedParamCount' => $this->classTypedParams[$className],
                        'totalParamCount' => $this->classTotalParams[$className],
                        'typedReturnCount' => $this->classTypedReturns[$className],
                        'totalReturnCount' => $this->classTotalReturns[$className],
                        'typedPropertyCount' => $this->classTypedProperties[$className],
                        'totalPropertyCount' => $this->classTotalProperties[$className],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->handleFunctionLeave(true);
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->handleFunctionLeave(false);
                break;
        }
    }

    public function afterTraverse(array $nodes): void
    {
        $coverage = $this->calculateCoverage(
            $this->fileTypedParams,
            $this->fileTotalParams,
            $this->fileTypedReturns,
            $this->fileTotalReturns,
            $this->fileTypedProperties,
            $this->fileTotalProperties,
        );

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            [
                'typeCoverage' => $coverage,
                'typedParamCount' => $this->fileTypedParams,
                'totalParamCount' => $this->fileTotalParams,
                'typedReturnCount' => $this->fileTypedReturns,
                'totalReturnCount' => $this->fileTotalReturns,
                'typedPropertyCount' => $this->fileTypedProperties,
                'totalPropertyCount' => $this->fileTotalProperties,
            ]
        );
    }

    private function handleFunctionEnter(Node\Stmt\ClassMethod|Node\Stmt\Function_ $node, bool $isMethod): void
    {
        if ($isMethod) {
            $className = end($this->currentClassName);
            $funcKey = $className . '::' . (string) $node->name;
        } else {
            $funcKey = (string) $node->namespacedName;
        }

        $this->currentFunctionName[] = $funcKey;
        $this->funcTypedParams[$funcKey] = 0;
        $this->funcTotalParams[$funcKey] = 0;
        $this->funcTypedReturns[$funcKey] = 0;
        $this->funcTotalReturns[$funcKey] = 1; // Every function has a return type slot

        // Return type
        if ($node->returnType !== null) {
            $this->funcTypedReturns[$funcKey] = 1;
            $this->fileTypedReturns++;
            if ($isMethod && count($this->currentClassName) > 0) {
                $this->classTypedReturns[end($this->currentClassName)]++;
            }
        }
        $this->fileTotalReturns++;
        if ($isMethod && count($this->currentClassName) > 0) {
            $this->classTotalReturns[end($this->currentClassName)]++;
        }

        // Parameters
        foreach ($node->getParams() as $param) {
            // PHP 8 promoted properties: count as property, not parameter
            if ($param->flags > 0 && $isMethod) {
                $className = end($this->currentClassName);
                $this->classTotalProperties[$className]++;
                $this->fileTotalProperties++;
                if ($param->type !== null) {
                    $this->classTypedProperties[$className]++;
                    $this->fileTypedProperties++;
                }
                continue;
            }

            $this->funcTotalParams[$funcKey]++;
            $this->fileTotalParams++;
            if ($isMethod && count($this->currentClassName) > 0) {
                $this->classTotalParams[end($this->currentClassName)]++;
            }

            if ($param->type !== null) {
                $this->funcTypedParams[$funcKey]++;
                $this->fileTypedParams++;
                if ($isMethod && count($this->currentClassName) > 0) {
                    $this->classTypedParams[end($this->currentClassName)]++;
                }
            }
        }
    }

    private function handleFunctionLeave(bool $isMethod): void
    {
        $funcKey = array_pop($this->currentFunctionName);

        $coverage = $this->calculateCoverage(
            $this->funcTypedParams[$funcKey],
            $this->funcTotalParams[$funcKey],
            $this->funcTypedReturns[$funcKey],
            $this->funcTotalReturns[$funcKey],
            0, 0 // No properties at function level
        );

        if ($isMethod) {
            $parts = explode('::', $funcKey, 2);
            $this->metricsController->setMetricValue(
                MetricCollectionTypeEnum::MethodCollection,
                ['path' => $parts[0], 'name' => $parts[1]],
                $coverage,
                'typeCoverage'
            );
        } else {
            $this->metricsController->setMetricValue(
                MetricCollectionTypeEnum::FunctionCollection,
                ['path' => $this->path, 'name' => $funcKey],
                $coverage,
                'typeCoverage'
            );
        }
    }

    private function calculateCoverage(
        int $typedParams, int $totalParams,
        int $typedReturns, int $totalReturns,
        int $typedProperties, int $totalProperties
    ): float {
        $totalElements = $totalParams + $totalReturns + $totalProperties;
        if ($totalElements === 0) {
            return 100.0;
        }

        // Weighted: Properties 40%, Returns 35%, Params 25%
        $paramCoverage = $totalParams > 0 ? $typedParams / $totalParams : 1.0;
        $returnCoverage = $totalReturns > 0 ? $typedReturns / $totalReturns : 1.0;
        $propertyCoverage = $totalProperties > 0 ? $typedProperties / $totalProperties : 1.0;

        // If no properties exist, redistribute weight to params and returns
        if ($totalProperties === 0) {
            $coverage = $paramCoverage * 0.4167 + $returnCoverage * 0.5833; // 25/60 and 35/60
        } else {
            $coverage = $paramCoverage * 0.25 + $returnCoverage * 0.35 + $propertyCoverage * 0.40;
        }

        return round($coverage * 100, 2);
    }
}
