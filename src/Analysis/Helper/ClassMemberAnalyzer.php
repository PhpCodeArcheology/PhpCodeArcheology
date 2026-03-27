<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis\Helper;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Collections\ConstantCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\PropertyCollection;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class ClassMemberAnalyzer
{
    public function __construct(
        private readonly MetricsController $metricsController,
        private readonly ParameterAnalyzer $parameterAnalyzer,
    ) {
    }

    /**
     * @param array<string, mixed>                                  $classInfo
     * @param array{path?: string, name?: string, files?: string[]} $classIdentifierData
     */
    public function handleClassMethods(
        array $classInfo,
        Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node,
        array $classIdentifierData): void
    {
        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            new FileNameCollection(),
            'methods'
        );

        $classMetricData = [
            MetricKey::METHOD_COUNT => 0,
            MetricKey::PRIVATE_COUNT => 0,
            MetricKey::PUBLIC_COUNT => 0,
            MetricKey::STATIC_COUNT => 0,
        ];

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            ++$classMetricData[MetricKey::METHOD_COUNT];

            $data = $this->handleClassMethod($classInfo, $stmt, $classIdentifierData);

            $classMetricData[MetricKey::PRIVATE_COUNT] += intval($data[MetricKey::PRIVATE]);
            $classMetricData[MetricKey::PUBLIC_COUNT] += intval($data[MetricKey::PUBLIC]);
            $classMetricData[MetricKey::STATIC_COUNT] += intval($data[MetricKey::STATIC]);
        }

        $projectMetrics = [
            MetricKey::OVERALL_METHODS_COUNT => MetricKey::METHOD_COUNT,
            MetricKey::OVERALL_PUBLIC_METHODS_COUNT => MetricKey::PUBLIC_COUNT,
            MetricKey::OVERALL_PRIVATE_METHODS_COUNT => MetricKey::PRIVATE_COUNT,
            MetricKey::OVERALL_STATIC_METHODS_COUNT => MetricKey::STATIC_COUNT,
        ];

        foreach ($projectMetrics as $projectMetric => $classMetricKey) {
            $incrementBy = $classMetricData[$classMetricKey];

            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $projectMetric,
                function (int|float|null $value) use ($incrementBy): float|int {
                    $value ??= 0;

                    return $value + $incrementBy;
                }
            );
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            $classMetricData
        );
    }

    /**
     * @param array<string, mixed>                                  $classInfo
     * @param array{path?: string, name?: string, files?: string[]} $classIdentifierData
     *
     * @return array{classInfo: array<string, mixed>, functionType: string, protected: bool, public: bool, private: bool, static: bool}
     */
    public function handleClassMethod(array $classInfo, Node\Stmt\ClassMethod $node, array $classIdentifierData): array
    {
        $methodIdentifierData = [
            'path' => $classIdentifierData['name'] ?? '',
            'name' => (string) $node->name,
        ];

        $methodMetricCollection = $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            $methodIdentifierData
        );

        $this->metricsController->setCollectionData(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods',
            (string) $methodMetricCollection->getIdentifier(),
            $methodIdentifierData['name']
        );

        $this->metricsController->setCollectionData(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            'methods',
            (string) $methodMetricCollection->getIdentifier(),
            $methodIdentifierData['name']
        );

        $this->parameterAnalyzer->handleParameters($node, $methodIdentifierData);
        $this->parameterAnalyzer->handleReturn($node, $methodIdentifierData);

        $methodData = [
            MetricKey::CLASS_INFO => $classInfo,
            MetricKey::FUNCTION_TYPE => 'method',
            MetricKey::PROTECTED => $node->isProtected(),
            MetricKey::PUBLIC => $node->isPublic(),
            MetricKey::PRIVATE => $node->isPrivate() || $node->isProtected(),
            MetricKey::STATIC => $node->isStatic(),
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::MethodCollection,
            $methodIdentifierData,
            $methodData
        );

        return $methodData;
    }

    /**
     * @param array<string, mixed>                                  $classInfo
     * @param array{path?: string, name?: string, files?: string[]} $identifierData
     */
    public function handleClassProperties(array $classInfo, Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node, array $identifierData): void
    {
        $propertyCollection = new PropertyCollection();

        foreach ($node->getProperties() as $property) {
            $this->handlePropOrConst($property, 'props', $propertyCollection);
        }

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $propertyCollection,
            'properties'
        );

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            count($propertyCollection),
            MetricKey::PROPERTY_COUNT
        );
    }

    /**
     * @param array<string, mixed>                                  $classInfo
     * @param array{path?: string, name?: string, files?: string[]} $identifierData
     */
    public function handleClassConstants(array $classInfo, Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node, array $identifierData): void
    {
        $constantCollection = new ConstantCollection();

        foreach ($node->getConstants() as $constant) {
            $this->handlePropOrConst($constant, 'consts', $constantCollection);
        }

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $constantCollection,
            'constants'
        );

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            count($constantCollection),
            MetricKey::CONSTANT_COUNT
        );
    }

    public function handlePropOrConst(Node\Stmt\Property|Node\Stmt\ClassConst $element, string $arrayKey, CollectionInterface $collection): void
    {
        $docBlock = $element->getDocComment();
        $docBlockText = $docBlock instanceof \PhpParser\Comment\Doc ? $docBlock->getText() : '';

        $docBlockText = str_replace('*/', '', $docBlockText);
        $docBlockText = preg_replace('/^\s*\*\s?/m', '', $docBlockText);

        $pattern = '/@var\s+([^\s]+)(?:\s+(.*))?/ms';

        $docBlockVar = [];
        if (preg_match($pattern, (string) $docBlockText, $matches)) {
            $docBlockVar = [
                'type' => $matches[1],
                'comment' => $matches[2] ?? '',
            ];
        }

        $scope = $element->isPrivate() ? 'private' : 'public';
        $scope = $element->isProtected() ? 'protected' : $scope;

        $propNamePrefix = $element instanceof Node\Stmt\Property ? '$' : '';

        $props = $element instanceof Node\Stmt\Property ? $element->props : $element->consts;
        foreach ($props as $prop) {
            $propName = $propNamePrefix.$prop->name->toString();
            $propType = $this->parameterAnalyzer->getTypeName($element->type);
            $propComment = '';

            $value = null;
            $valueType = null;

            if ($element instanceof Node\Stmt\ClassConst && $prop instanceof Node\Const_) {
                $value = $prop->value->getAttribute('rawValue');

                $valueType = match (true) {
                    $prop->value instanceof Node\Expr\Array_ => 'array',
                    $prop->value instanceof Node\Expr\ConstFetch, $prop->value instanceof Node\Expr\ClassConstFetch => 'constant',
                    default => match (true) {
                        $prop->value instanceof Node\Scalar\Int_,
                        $prop->value instanceof Node\Scalar\Float_,
                        $prop->value instanceof Node\Scalar\String_ => gettype($prop->value->value),
                        default => '',
                    },
                };

                if ('' === $valueType) {
                    $prettyPrinter = new Standard();
                    $value = $prettyPrinter->prettyPrintExpr($prop->value);
                }

                if ('constant' === $valueType) {
                    if ($prop->value instanceof Node\Expr\ConstFetch) {
                        $value = $prop->value->name->toString();
                    } elseif ($prop->value instanceof Node\Expr\ClassConstFetch && $prop->value->name instanceof Node\Identifier) {
                        $value = $prop->value->name->toString();
                    }
                    $valueType = '';
                }
            }

            if (count($docBlockVar) > 0) {
                $propType = $docBlockVar['type'];
                $propComment = $docBlockVar['comment'];
            }

            $collection->set([
                'name' => $propName,
                'type' => $propType,
                'comment' => $propComment,
                'scope' => $scope,
                'value' => $value,
                'valueType' => $valueType,
            ]);
        }
    }
}
