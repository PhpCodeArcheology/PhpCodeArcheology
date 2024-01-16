<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\EnumNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\MethodNameCollection;
use PhpCodeArch\Metrics\Model\Collections\ParameterCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;
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

    public function init()
    {
        $projectCollections = [
            'classes' => new ClassNameCollection(),
            'interfaces' => new InterfaceNameCollection(),
            'traits' => new TraitNameCollection(),
            'enums' => new EnumNameCollection(),
            'functions' => new FunctionNameCollection(),
            'methods' => new MethodNameCollection(),
        ];

        foreach ($projectCollections as $key => $collection) {
            $this->metricsController->setCollection(
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

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'name' =>(string) $node->namespacedName,
                        'path' => $this->path,
                    ],
                    $this->outputCount['functions'],
                    'outputCount'
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

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'name' =>(string) $node->namespacedName,
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
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $this->outputCount['overall'],
            'overallOutputStatements'
        );

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->outputCount['file'],
            'outputCount'
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

        foreach ($node->getParams() as $parameter) {
            $type = null;

            if ($parameter->type !== null) {
                switch (true) {
                    case $parameter->type instanceof Node\Name\FullyQualified:
                    case $parameter->type instanceof Node\Name:
                        $type = getNodeName($parameter->type);
                        break;

                    case $parameter->type instanceof Node\Identifier:
                    case $parameter->type instanceof Node\Expr\Variable:
                        $type = $parameter->type->name;
                        break;
                }
            }

            $parameterCollection->set([
                'name' => '$' . (string) $parameter->var->name,
                'type' => $type,
            ]);
        }

        $metricsType = $node instanceof Node\Stmt\ClassMethod ? MetricCollectionTypeEnum::ClassCollection : MetricCollectionTypeEnum::FunctionCollection;

        $this->metricsController->setCollection(
            $metricsType,
            $identifierData,
            $parameterCollection,
            'parameters'
        );
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

        $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData
        );

        $metricData = [
            'singleName' => (string) $node->name,
            'namespace' => $namespace,
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData,
            $metricData
        );


        $this->handleParameters($node, $identifierData);

        $this->metricsController->changeMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'overallFunctionCount',
            'PhpCodeArch\incrementOr1IfNull'
        );

        $this->inFunction = true;
        $this->outputCount['functions'] = 0;
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
        $namespace = str_replace((string) $node->name, '', (string) $node->namespacedName);
        $namespace = rtrim($namespace, '\\');

        $identifierData = [
            'path' => $this->path,
            'name' => $className,
        ];

        $classMetricCollection = $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData
        );

        $className = $classMetricCollection->getName();
        $classId = (string) $classMetricCollection->getIdentifier();

        $classMetricsData = [
            'interface' => false,
            'trait' => false,
            'abstract' => false,
            'enum' => false,
            'final' => false,
            'realClass' => false,
            'anonymous' => str_starts_with($className, 'anonymous@'),
            'singleName' => (string) $node->name,
            'namespace' => $namespace,
        ];

        if (method_exists($node, 'isFinal') && $node->isFinal()) {
            $classMetricsData['final'] = true;
        }

        if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                'overallAbstractClasses',
                'PhpCodeArch\incrementOr1IfNull'
            );

            $classMetricsData['abstract'] = true;
        }

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
                $this->metricsController->changeMetricValue(
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

                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'classes',
                    $classId,
                    $className
                );

                $classMetricsData['realClass'] = true;
                break;

            case $node instanceof Node\Stmt\Enum_:
                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'enums',
                    $classId,
                    $className
                );

                $classMetricsData['enum'] = true;
                break;

            case $node instanceof Node\Stmt\Interface_:
                $this->metricsController->changeMetricValue(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'overallInterfaces',
                    'PhpCodeArch\incrementOr1IfNull'
                );

                $this->metricsController->setCollectionData(
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
                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'traits',
                    $classId,
                    $className
                );

                $classMetricsData['trait'] = true;
                break;
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $classMetricsData
        );

        $this->handleClassMethods($node, $identifierData);
    }

    private function handleClassMethods(
        Node\Stmt\Class_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Interface_ $node,
        array $classIdentifierData): void
    {

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $classIdentifierData,
            new MethodNameCollection(),
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

            $data = $this->handleClassMethod($stmt, $classIdentifierData);

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

    private function handleClassMethod(Node\Stmt\ClassMethod $node, array $classIdentifierData): array
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

        $this->handleParameters($node, $methodIdentifierData);

        $methodData = [
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
}
