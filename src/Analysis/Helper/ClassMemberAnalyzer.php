<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis\Helper;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\Collections\ConstantCollection;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\PropertyCollection;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use function PhpCodeArch\getNodeName;

class ClassMemberAnalyzer
{
    public function __construct(
        private readonly MetricsController $metricsController,
        private readonly ParameterAnalyzer $parameterAnalyzer,
    ) {
    }

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
            'methodCount' => 0,
            'privateCount' => 0,
            'publicCount' => 0,
            'staticCount' => 0,
        ];

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            ++ $classMetricData['methodCount'];

            $data = $this->handleClassMethod($classInfo, $stmt, $classIdentifierData);

            $classMetricData['privateCount'] += intval($data['private']);
            $classMetricData['publicCount'] += intval($data['public']);
            $classMetricData['staticCount'] += intval($data['static']);
        }

        $projectMetrics = [
            'overallMethodsCount' => 'methodCount',
            'overallPublicMethodsCount' => 'publicCount',
            'overallPrivateMethodsCount' => 'privateCount',
            'overallStaticMethodsCount' => 'staticCount',
        ];

        foreach ($projectMetrics as $projectMetric => $classMetricKey) {
            $incrementBy = $classMetricData[$classMetricKey];

            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $projectMetric,
                function($value) use ($incrementBy) {
                    $value = $value ?? 0;
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

    public function handleClassMethod(array $classInfo, Node\Stmt\ClassMethod $node, array $classIdentifierData): array
    {
        $methodIdentifierData = [
            'path' => $classIdentifierData['name'],
            'name' => (string)$node->name,
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
            $methodMetricCollection->getName()
        );

        $this->metricsController->setCollectionData(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            'methods',
            (string) $methodMetricCollection->getIdentifier(),
            $methodMetricCollection->getName()
        );

        $this->parameterAnalyzer->handleParameters($node, $methodIdentifierData);
        $this->parameterAnalyzer->handleReturn($node, $methodIdentifierData);

        $methodData = [
            'classInfo' => $classInfo,
            'functionType' => 'method',
            'protected' => $node->isProtected(),
            'public' => $node->isPublic(),
            'private' => $node->isPrivate() || $node->isProtected(),
            'static' => $node->isStatic(),
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::MethodCollection,
            $methodIdentifierData,
            $methodData
        );

        return $methodData;
    }

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
            'propertyCount'
        );
    }

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
            'constantCount'
        );
    }

    public function handlePropOrConst(Node\Stmt\Property|Node\Stmt\ClassConst $element, string $arrayKey, CollectionInterface $collection): void
    {
        $docBlock = $element->getDocComment();
        $docBlockText = $docBlock ? $docBlock->getText() : '';

        $docBlockText = str_replace('*/', '', $docBlockText);
        $docBlockText = preg_replace('/^\s*\*\s?/m', '', $docBlockText);

        $pattern = '/@var\s+([^\s]+)(?:\s+(.*))?/ms';

        $docBlockVar = [];
        if (preg_match($pattern, $docBlockText, $matches)) {
            $docBlockVar = [
                'type' => $matches[1],
                'comment' => $matches[2] ?? '',
            ];
        }

        $scope = $element->isPrivate() ? 'private' : 'public';
        $scope = $element->isProtected() ? 'protected' : $scope;

        $propNamePrefix = $element instanceof Node\Stmt\Property ? '$' : '';

        foreach ($element->{$arrayKey} as $prop) {
            $propName = $propNamePrefix . $prop->name->toString();
            $propType = $this->parameterAnalyzer->getTypeName($element->type);
            $propComment = '';

            $value = null;
            $valueType = null;

            if ($element instanceof Node\Stmt\ClassConst) {
                $value = $prop->value->getAttribute('rawValue');

                $valueType = match (true) {
                    $prop->value instanceof Node\Expr\Array_ => 'array',
                    $prop->value instanceof Node\Expr\ConstFetch, $prop->value instanceof Node\Expr\ClassConstFetch => 'constant',
                    default => isset($prop->value->value) ? gettype($prop->value->value) : '',
                };

                if ($valueType === '') {
                    $prettyPrinter = new Standard();
                    $value = $prettyPrinter->prettyPrintExpr($prop->value);
                }

                if ($valueType === 'constant') {
                    $value = $prop->value->name->toString();
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
