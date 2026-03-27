<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class TypeCoverageVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /** @var array<int, string> */
    private array $currentClassName = [];
    /** @var array<int, string> */
    private array $currentFunctionName = [];

    // File-level counters
    private int $fileTypedParams = 0;
    private int $fileTotalParams = 0;
    private int $fileTypedReturns = 0;
    private int $fileTotalReturns = 0;
    private int $fileTypedProperties = 0;
    private int $fileTotalProperties = 0;

    // Class-level counters
    /** @var array<string, int> */
    private array $classTypedParams = [];
    /** @var array<string, int> */
    private array $classTotalParams = [];
    /** @var array<string, int> */
    private array $classTypedReturns = [];
    /** @var array<string, int> */
    private array $classTotalReturns = [];
    /** @var array<string, int> */
    private array $classTypedProperties = [];
    /** @var array<string, int> */
    private array $classTotalProperties = [];

    // Function/Method-level counters
    /** @var array<string, int> */
    private array $funcTypedParams = [];
    /** @var array<string, int> */
    private array $funcTotalParams = [];
    /** @var array<string, int> */
    private array $funcTypedReturns = [];
    /** @var array<string, int> */
    private array $funcTotalReturns = [];

    public function beforeTraverse(array $nodes): ?array
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

                    if ($node->type instanceof Node) {
                        $this->classTypedProperties[$className] += $propCount;
                        $this->fileTypedProperties += $propCount;
                    }
                }
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
                        MetricKey::TYPE_COVERAGE => $coverage,
                        MetricKey::TYPED_PARAM_COUNT => $this->classTypedParams[$className],
                        MetricKey::TOTAL_PARAM_COUNT => $this->classTotalParams[$className],
                        MetricKey::TYPED_RETURN_COUNT => $this->classTypedReturns[$className],
                        MetricKey::TOTAL_RETURN_COUNT => $this->classTotalReturns[$className],
                        MetricKey::TYPED_PROPERTY_COUNT => $this->classTypedProperties[$className],
                        MetricKey::TOTAL_PROPERTY_COUNT => $this->classTotalProperties[$className],
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

        return null;
    }

    public function afterTraverse(array $nodes): ?array
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
                MetricKey::TYPE_COVERAGE => $coverage,
                MetricKey::TYPED_PARAM_COUNT => $this->fileTypedParams,
                MetricKey::TOTAL_PARAM_COUNT => $this->fileTotalParams,
                MetricKey::TYPED_RETURN_COUNT => $this->fileTypedReturns,
                MetricKey::TOTAL_RETURN_COUNT => $this->fileTotalReturns,
                MetricKey::TYPED_PROPERTY_COUNT => $this->fileTypedProperties,
                MetricKey::TOTAL_PROPERTY_COUNT => $this->fileTotalProperties,
            ]
        );

        return null;
    }

    private function handleFunctionEnter(Node\Stmt\ClassMethod|Node\Stmt\Function_ $node, bool $isMethod): void
    {
        if ($isMethod) {
            $className = end($this->currentClassName);
            if (false === $className) {
                return;
            }
            $funcKey = $className.'::'.$node->name;
        } else {
            $funcKey = $node instanceof Node\Stmt\Function_
                ? (string) $node->namespacedName
                : (string) $node->name;
        }

        $this->currentFunctionName[] = $funcKey;
        $this->funcTypedParams[$funcKey] = 0;
        $this->funcTotalParams[$funcKey] = 0;
        $this->funcTypedReturns[$funcKey] = 0;
        $this->funcTotalReturns[$funcKey] = 1; // Every function has a return type slot

        // Return type
        if ($node->returnType instanceof Node) {
            $this->funcTypedReturns[$funcKey] = 1;
            ++$this->fileTypedReturns;
            if ($isMethod && count($this->currentClassName) > 0) {
                ++$this->classTypedReturns[end($this->currentClassName)];
            }
        }
        ++$this->fileTotalReturns;
        if ($isMethod && count($this->currentClassName) > 0) {
            ++$this->classTotalReturns[end($this->currentClassName)];
        }

        // Parameters
        foreach ($node->getParams() as $param) {
            // PHP 8 promoted properties: count as property, not parameter
            if ($param->flags > 0 && $isMethod) {
                $className = end($this->currentClassName);
                if (false === $className) {
                    continue;
                }
                ++$this->classTotalProperties[$className];
                ++$this->fileTotalProperties;
                if (null !== $param->type) {
                    ++$this->classTypedProperties[$className];
                    ++$this->fileTypedProperties;
                }
                continue;
            }

            ++$this->funcTotalParams[$funcKey];
            ++$this->fileTotalParams;
            if ($isMethod && count($this->currentClassName) > 0) {
                ++$this->classTotalParams[end($this->currentClassName)];
            }

            if (null !== $param->type) {
                ++$this->funcTypedParams[$funcKey];
                ++$this->fileTypedParams;
                if ($isMethod && count($this->currentClassName) > 0) {
                    ++$this->classTypedParams[end($this->currentClassName)];
                }
            }
        }
    }

    private function handleFunctionLeave(bool $isMethod): void
    {
        $funcKey = array_pop($this->currentFunctionName);
        if (null === $funcKey) {
            return;
        }

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
                ['path' => $parts[0], 'name' => $parts[1] ?? ''],
                $coverage,
                MetricKey::TYPE_COVERAGE
            );
        } else {
            $this->metricsController->setMetricValue(
                MetricCollectionTypeEnum::FunctionCollection,
                ['path' => $this->path, 'name' => $funcKey],
                $coverage,
                MetricKey::TYPE_COVERAGE
            );
        }
    }

    private function calculateCoverage(
        int $typedParams, int $totalParams,
        int $typedReturns, int $totalReturns,
        int $typedProperties, int $totalProperties,
    ): float {
        $totalElements = $totalParams + $totalReturns + $totalProperties;
        if (0 === $totalElements) {
            return 100.0;
        }

        // Weighted: Properties 40%, Returns 35%, Params 25%
        $paramCoverage = $totalParams > 0 ? $typedParams / $totalParams : 1.0;
        $returnCoverage = $totalReturns > 0 ? $typedReturns / $totalReturns : 1.0;
        $propertyCoverage = $totalProperties > 0 ? $typedProperties / $totalProperties : 1.0;

        // If no properties exist, redistribute weight to params and returns
        if (0 === $totalProperties) {
            $coverage = $paramCoverage * 0.4167 + $returnCoverage * 0.5833; // 25/60 and 35/60
        } else {
            $coverage = $paramCoverage * 0.25 + $returnCoverage * 0.35 + $propertyCoverage * 0.40;
        }

        return round($coverage * 100, 2);
    }
}
