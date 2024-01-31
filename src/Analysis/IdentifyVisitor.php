<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Collections\ConstantCollection;
use PhpCodeArch\Metrics\Model\Collections\EnumNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\ParameterCollection;
use PhpCodeArch\Metrics\Model\Collections\PropertyCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter\Standard;
use function PhpCodeArch\getNodeName;

class IdentifyVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    /**
     * @var string[]
     */
    private array $classes = [];

    /**
     * @var string[]
     */
    private array $interfaces = [];

    /**
     * @var string[]
     */
    private array $traits = [];

    /**
     * @var string[]
     */
    private array $enums = [];

    /**
     * @var string[]
     */
    private array $functions = [];

    /**
     * @var string[]
     */
    private array $methods = [];

    /**
     * @var bool
     */
    private bool $inFunction = false;

    /**
     * @var bool
     */
    private bool $inClass = false;

    /**
     * @var int[]
     */
    private array $outputCount = [
        'overall' => 0,
        'file' => 0,
        'classes' => 0,
        'functions' => 0,
        'methods' => 0,
    ];

    public function init(): void
    {
        $projectCollections = [
            'classes' => new ClassNameCollection(),
            'interfaces' => new InterfaceNameCollection(),
            'traits' => new TraitNameCollection(),
            'enums' => new EnumNameCollection(),
            'functions' => new FunctionNameCollection(),
            'methods' => new FileNameCollection(),
        ];

        foreach ($projectCollections as $key => $collection) {
            $this->repository->saveCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collection,
                $key
            );
        }
    }

    /**
     * @param Node[] $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->outputCount['file'] = 0;
        $this->functions = [];
        $this->classes = [];
    }

    /**
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Function_:
                $this->setFunctionMetrics($node);
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->inFunction = true;
                $this->outputCount['methods'] = 0;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->setClassMetrics($node);
                break;
        }
    }

    /**
     * Mainly used for setting and saving output counts
     *
     * @param Node $node
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Echo_:
            case $node instanceof Node\Expr\Print_:
                // Count numbers of output statements

                $this->countOutput();
                break;

            case $node instanceof Node\Expr\FuncCall:
                // Count printf as output statement
                $functionName = $node->name instanceof Node\Name ? $node->name->toString() : null;
                if ($functionName === 'printf') {
                    $this->countOutput();
                }
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->inFunction = false;

                $this->repository->saveMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'name' =>(string) $node->namespacedName,
                        'path' => $this->path,
                    ],
                    [
                        'functionType' => 'function',
                        'outputCount' => $this->outputCount['functions'],
                    ]
                );

                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $this->inFunction = false;
                break;

            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $this->inClass = false;

                $this->repository->saveMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'name' => ClassName::ofNode($node)->__toString(),
                        'path' => $this->path,
                    ],
                    $this->outputCount['classes'],
                    'outputCount'
                );

                break;
        }
    }

    /**
     * Save metrics
     *
     * @param Node[] $nodes
     * @return void
     */
    public function afterTraverse(array $nodes): void
    {
        $this->repository->saveMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $this->outputCount['overall'],
            'overallOutputStatements'
        );

        $this->repository->saveMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->outputCount['file'],
            'outputCount'
        );

        $this->repository->saveCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            new FunctionNameCollection($this->functions),
            'functions'
        );

        $this->repository->saveCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            new ClassNameCollection($this->classes),
            'classes'
        );
    }

    /**
     * Count output statements
     *
     * @return void
     */
    private function countOutput(): void
    {
        ++ $this->outputCount['overall'];
        ++ $this->outputCount['file'];

        if ($this->inClass) {
            ++ $this->outputCount['classes'];

            if ($this->inFunction) {
                ++ $this->outputCount['methods'];
            }
        }
        elseif ($this->inFunction) {
            ++ $this->outputCount['functions'];
        }
    }

    /**
     * Set parameters on functions and class methods
     *
     * @param Node\Stmt\Function_|Node\Stmt\ClassMethod $node
     * @param array $identifierData
     * @return void
     */
    private function handleParameters(
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

        $this->repository->saveCollection(
            $metricsType,
            $identifierData,
            $parameterCollection,
            'parameters'
        );
    }

    private function getTypeName(mixed $type): string
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

    /**
     * @param Node\Stmt\Function_ $node
     */
    private function setFunctionMetrics(Node\Stmt\Function_ $node): void
    {
        $namespace = str_replace((string) $node->name, '', (string) $node->namespacedName);
        $namespace = rtrim($namespace, '\\');

        $identifierData = [
            'path' => $this->path,
            'name' => (string) $node->namespacedName,
        ];

        $fnMetricCollection = $this->repository->createMetricCollection(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData
        );

        $metricData = [
            'singleName' => (string) $node->name,
            'namespace' => $namespace,
        ];

        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData,
            $metricData
        );

        $this->handleParameters($node, $identifierData);
        $this->handleReturn($node, $identifierData);

        $this->repository->changeMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'overallFunctionCount',
            'PhpCodeArch\incrementOr1IfNull'
        );

        $this->repository->setCollectionData(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'functions',
            (string) $fnMetricCollection->getIdentifier(),
            (string) $node->namespacedName
        );


        $this->inFunction = true;
        $this->outputCount['functions'] = 0;
        $this->functions[(string) $fnMetricCollection->getIdentifier()] = (string) $node->namespacedName;
    }

    /**
     * @param Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node
     * @return void
     */
    private function setClassMetrics(
        Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node): void
    {
        $this->inClass = true;
        $this->outputCount['classes'] = 0;

        $className = (string) ClassName::ofNode($node);
        $namespace = str_replace((string) $node->name, '', ClassName::ofNode($node)->__toString());
        $namespace = rtrim($namespace, '\\');
        $singleName = (string) $node->name;

        $identifierData = [
            'path' => $this->path,
            'name' => $className,
        ];

        $classMetricCollection = $this->repository->createMetricCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData
        );

        $className = $classMetricCollection->getName();
        $classId = (string) $classMetricCollection->getIdentifier();

        $classInfo = [
            'id' => $classId,
            'name' => $className,
            'namespace' => $namespace,
            'singleName' => $singleName,
        ];

        $classMetricsData = [
            'interface' => false,
            'trait' => false,
            'abstract' => false,
            'enum' => false,
            'final' => false,
            'realClass' => false,
            'anonymous' => str_starts_with($className, 'anonymous@'),
            'singleName' => $singleName,
            'namespace' => $namespace,
        ];

        if (method_exists($node, 'isFinal') && $node->isFinal()) {
            $classMetricsData['final'] = true;
        }

        if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
            $this->repository->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                'overallAbstractClasses',
                'PhpCodeArch\incrementOr1IfNull'
            );

            $classMetricsData['abstract'] = true;
        }

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
                $this->repository->changeMetricValue(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'overallClasses',
                    function($value) {
                        if (! $value) {
                            return 1;
                        }

                        return $value + 1;
                    }
                );

                $this->repository->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'classes',
                    $classId,
                    $className
                );

                $classMetricsData['realClass'] = true;
                break;

            case $node instanceof Node\Stmt\Enum_:
                $this->repository->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'enums',
                    $classId,
                    $className
                );

                $classMetricsData['enum'] = true;
                break;

            case $node instanceof Node\Stmt\Interface_:
                $this->repository->changeMetricValue(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'overallInterfaces',
                    'PhpCodeArch\incrementOr1IfNull'
                );

                $this->repository->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'interfaces',
                    $classId,
                    $className
                );

                $classMetricsData['interface'] = true;
                $classMetricsData['abstract'] = true;
                break;

            case $node instanceof Node\Stmt\Trait_:
                $this->repository->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'traits',
                    $classId,
                    $className
                );

                $classMetricsData['trait'] = true;
                break;
        }

        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $classMetricsData
        );

        $this->handleClassMethods($classInfo, $node, $identifierData);
        $this->handleClassConstants($classInfo, $node, $identifierData);
        $this->handleClassProperties($classInfo, $node, $identifierData);

        $this->classes[$classId] = $className;
    }

    private function handleClassMethods(
        array $classInfo,
        Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node,
        array $classIdentifierData): void
    {

        $this->repository->saveCollection(
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
            'overAllMethodsCount' => 'methodCount',
            'overAllPublicMethodsCount' => 'publicCount',
            'overAllPrivateMethodsCount' => 'privateCount',
            'overAllStaticMethodsCount' => 'staticCount',
        ];

        foreach ($projectMetrics as $projectMetric => $classMetricKey) {
            $incrementBy = $classMetricData[$classMetricKey];

            $this->repository->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $projectMetric,
                function($value) use ($incrementBy) {
                    $value = $value ?? 0;
                    return $value + $incrementBy;
                }
            );
        }

        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            $classMetricData
        );
    }

    private function handleClassMethod(array $classInfo, Node\Stmt\ClassMethod $node, array $classIdentifierData): array
    {
        $methodIdentifierData = [
            'path' => $classIdentifierData['name'],
            'name' => (string)$node->name,
        ];

        $methodMetricCollection = $this->repository->createMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            $methodIdentifierData
        );

        $this->repository->setCollectionData(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'methods',
            (string) $methodMetricCollection->getIdentifier(),
            $methodMetricCollection->getName()
        );

        $this->repository->setCollectionData(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            'methods',
            (string) $methodMetricCollection->getIdentifier(),
            $methodMetricCollection->getName()
        );

        $this->handleParameters($node, $methodIdentifierData);
        $this->handleReturn($node, $methodIdentifierData);

        $methodData = [
            'classInfo' => $classInfo,
            'functionType' => 'method',
            'protected' => $node->isProtected(),
            'public' => $node->isPublic(),
            'private' => $node->isPrivate() || $node->isProtected(),
            'static' => $node->isStatic(),
        ];

        $this->repository->saveMetricValues(
            MetricCollectionTypeEnum::MethodCollection,
            $methodIdentifierData,
            $methodData
        );

        return $methodData;
    }

    private function handleReturn(
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

        $this->repository->saveMetricValue(
            $metricsType,
            $identifierData,
            $returnType,
            'returnType'
        );
    }

    private function handleClassProperties(array $classInfo, Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node, array $identifierData): void
    {
        $propertyCollection = new PropertyCollection();

        foreach ($node->getProperties() as $property) {
            $this->handlePropOrConst($property, 'props', $propertyCollection);
        }

        $this->repository->saveCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $propertyCollection,
            'properties'
        );

        $this->repository->saveMetricValue(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            count($propertyCollection),
            'propertyCount'
        );
    }

    private function handleClassConstants(array $classInfo, Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_|Node\Stmt\Class_ $node, array $identifierData): void
    {
        $constantCollection = new ConstantCollection();

        foreach ($node->getConstants() as $constant) {
            $this->handlePropOrConst($constant, 'consts', $constantCollection);
        }

        $this->repository->saveCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $constantCollection,
            'constants'
        );

        $this->repository->saveMetricValue(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            count($constantCollection),
            'constantCount'
        );
    }

    private function handlePropOrConst(Node\Stmt\Property|Node\Stmt\ClassConst $element, string $arrayKey, CollectionInterface $collection): void
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
            $propType = $this->getTypeName($element->type);
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
