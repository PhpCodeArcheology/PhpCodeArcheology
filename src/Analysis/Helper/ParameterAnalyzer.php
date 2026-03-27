<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis\Helper;

use function PhpCodeArch\getNodeName;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\ParameterCollection;
use PhpParser\Node;

class ParameterAnalyzer
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    /**
     * Set parameters on functions and class methods.
     *
     * @param array{path?: string, name?: string} $identifierData
     */
    public function handleParameters(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $node,
        array $identifierData): void
    {
        $parameterCollection = new ParameterCollection();

        $docBlock = $node->getDocComment();
        $docBlockText = $docBlock instanceof \PhpParser\Comment\Doc ? $docBlock->getText() : '';

        $docBlockText = str_replace('*/', '', $docBlockText);
        $docBlockText = preg_replace('/^\s*\*\s?/m', '', $docBlockText);

        $pattern = '/@param\s+([^\s]+)\s+(\$[^\s]+)(?:\s+([^@]*))?/ms';
        preg_match_all($pattern, (string) $docBlockText, $matches, PREG_SET_ORDER);

        $paramDetails = [];
        foreach ($matches as $match) {
            $paramDetails[$match[2]] = [
                'type' => $match[1],
                'variable' => $match[2],
                'description' => trim($match[3] ?? ''),
            ];
        }

        $totalParams = 0;
        $optionalParams = 0;
        $nullableParams = 0;

        foreach ($node->getParams() as $parameter) {
            // Skip promoted properties — counted as properties, not parameters
            if ($parameter->flags > 0 && $node instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            $type = null;

            if (null !== $parameter->type) {
                $type = $this->getTypeName($parameter->type);
            }

            $paramVar = $parameter->var;
            if (!$paramVar instanceof Node\Expr\Variable || !is_string($paramVar->name)) {
                continue;
            }
            $name = '$'.$paramVar->name;

            if (null === $type && isset($paramDetails[$name])) {
                $type = $paramDetails[$name]['type'];
            }

            ++$totalParams;
            if (null !== $parameter->default) {
                ++$optionalParams;
            }
            if ($parameter->type instanceof Node\NullableType) {
                ++$nullableParams;
            }

            $parameterCollection->set([
                'name' => $name,
                'type' => $type,
                'description' => isset($paramDetails[$name]) ? $paramDetails[$name]['description'] : '',
            ]);
        }

        $metricsType = $node instanceof Node\Stmt\ClassMethod ? MetricCollectionTypeEnum::MethodCollection : MetricCollectionTypeEnum::FunctionCollection;

        $this->metricsController->setCollection(
            $metricsType,
            $identifierData,
            $parameterCollection,
            'parameters'
        );

        $this->metricsController->setMetricValues(
            $metricsType,
            $identifierData,
            [
                MetricKey::PARAMETER_COUNT => $totalParams,
                MetricKey::OPTIONAL_PARAMETER_COUNT => $optionalParams,
                MetricKey::NULLABLE_PARAMETER_COUNT => $nullableParams,
            ]
        );
    }

    /**
     * @param array{path?: string, name?: string} $identifierData
     */
    public function handleReturn(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $node,
        array $identifierData): void
    {
        $returnType = 'not specified';

        $docBlock = $node->getDocComment();
        $docBlockText = $docBlock instanceof \PhpParser\Comment\Doc ? $docBlock->getText() : '';
        if (preg_match('/^\s*\* @return (.*)/m', $docBlockText, $matches)) {
            $returnType = trim($matches[1]);
        }

        if (isset($node->returnType)) {
            $returnType = $this->getTypeName($node->returnType);
        }

        $metricsType = $node instanceof Node\Stmt\ClassMethod ? MetricCollectionTypeEnum::MethodCollection : MetricCollectionTypeEnum::FunctionCollection;

        $this->metricsController->setMetricValue(
            $metricsType,
            $identifierData,
            $returnType,
            MetricKey::RETURN_TYPE
        );
    }

    public function getTypeName(mixed $type): string
    {
        switch (true) {
            case $type instanceof Node\Name\FullyQualified:
            case $type instanceof Node\Name:
            case $type instanceof Node\NullableType:
                return getNodeName($type) ?? 'mixed';

            case $type instanceof Node\Identifier:
                return $type->name;

            case $type instanceof Node\Expr\Variable:
                $varName = $type->name;
                if (!is_string($varName)) {
                    return 'mixed';
                }

                return $varName;

            case $type instanceof Node\UnionType:
                $types = array_map(fn (\PhpParser\Node\Identifier|\PhpParser\Node\IntersectionType|Node\Name $type) => $this->getTypeName($type), $type->types);

                return implode('|', $types);
        }

        return 'implicit mixed';
    }
}
