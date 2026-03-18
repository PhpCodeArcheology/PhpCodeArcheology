<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis\Helper;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\Collections\ParameterCollection;
use PhpParser\Node;
use function PhpCodeArch\getNodeName;

class ParameterAnalyzer
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    /**
     * Set parameters on functions and class methods
     *
     * @param Node\Stmt\Function_|Node\Stmt\ClassMethod $node
     * @param array $identifierData
     * @return void
     */
    public function handleParameters(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $node,
        array $identifierData): void
    {
        $parameterCollection = new ParameterCollection();

        $docBlock = $node->getDocComment();
        $docBlockText = $docBlock ? $docBlock->getText() : '';

        $docBlockText = str_replace('*/', '', $docBlockText);
        $docBlockText = preg_replace('/^\s*\*\s?/m', '', $docBlockText);

        $pattern = '/@param\s+([^\s]+)\s+(\$[^\s]+)(?:\s+([^@]*))?/ms';
        preg_match_all($pattern, $docBlockText, $matches, PREG_SET_ORDER);

        $paramDetails = [];
        foreach ($matches as $match) {
            $paramDetails[$match[2]] = [
                'type' => $match[1],
                'variable' => $match[2],
                'description' => trim($match[3] ?? '')
            ];
        }

        foreach ($node->getParams() as $parameter) {
            $type = null;

            if ($parameter->type !== null) {
                $type = $this->getTypeName($parameter->type);
            }

            $name = '$' . (string) $parameter->var->name;

            if ($type === null && isset($paramDetails[$name])) {
                $type = $paramDetails[$name]['type'];
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
    }

    public function handleReturn(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $node,
        array $identifierData): void
    {
        $returnType = 'not specified';

        $docBlock = $node->getDocComment();
        $docBlockText = $docBlock ? $docBlock->getText() : '';
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
            'returnType'
        );
    }

    public function getTypeName(mixed $type): string
    {
        switch (true) {
            case $type instanceof Node\Name\FullyQualified:
            case $type instanceof Node\Name:
            case $type instanceof Node\NullableType:
                return getNodeName($type);

            case $type instanceof Node\Identifier:
            case $type instanceof Node\Expr\Variable:
                return $type->name;

            case $type instanceof Node\UnionType:
                $types = array_map(function($type) {
                    return $this->getTypeName($type);
                }, $type->types);
                return implode('|', $types);
        }

        return 'implicit mixed';
    }
}
