<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Analysis\Helper\ClassMemberAnalyzer;
use PhpCodeArch\Analysis\Helper\ParameterAnalyzer;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\EnumNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class IdentifyVisitor implements NodeVisitor, VisitorInterface, InitializableVisitorInterface
{
    use VisitorTrait;

    /**
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * @var array<string, string>
     */
    private array $functions = [];

    private bool $inFunction = false;

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

    private ParameterAnalyzer $parameterAnalyzer;

    private ClassMemberAnalyzer $classMemberAnalyzer;

    public function init(): void
    {
        $this->parameterAnalyzer = new ParameterAnalyzer($this->metricsController);
        $this->classMemberAnalyzer = new ClassMemberAnalyzer($this->metricsController, $this->parameterAnalyzer);

        $projectCollections = [
            'classes' => new ClassNameCollection(),
            'interfaces' => new InterfaceNameCollection(),
            'traits' => new TraitNameCollection(),
            'enums' => new EnumNameCollection(),
            'functions' => new FunctionNameCollection(),
            'methods' => new FileNameCollection(),
        ];

        foreach ($projectCollections as $key => $collection) {
            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collection,
                $key
            );
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_INTERFACES => 0,
                MetricKey::OVERALL_ABSTRACT_CLASSES => 0,
            ]
        );
    }

    /**
     * @param Node[] $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->outputCount['file'] = 0;
        $this->outputCount['classes'] = 0;
        $this->outputCount['functions'] = 0;
        $this->outputCount['methods'] = 0;
        $this->functions = [];
        $this->classes = [];
        $this->inFunction = false;
        $this->inClass = false;

        return null;
    }

    public function enterNode(Node $node): int|Node|null
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

        return null;
    }

    /**
     * Mainly used for setting and saving output counts.
     */
    public function leaveNode(Node $node): int|Node|array|null
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
                if ('printf' === $functionName) {
                    $this->countOutput();
                }
                break;

            case $node instanceof Node\Stmt\Function_:
                $this->inFunction = false;

                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::FunctionCollection,
                    [
                        'name' => (string) $node->namespacedName,
                        'path' => $this->path,
                    ],
                    [
                        MetricKey::FUNCTION_TYPE => 'function',
                        MetricKey::OUTPUT_COUNT => $this->outputCount['functions'],
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

                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::ClassCollection,
                    [
                        'name' => ClassName::ofNode($node)->__toString(),
                        'path' => $this->path,
                    ],
                    $this->outputCount['classes'],
                    MetricKey::OUTPUT_COUNT
                );

                break;
        }

        return null;
    }

    /**
     * Save metrics.
     *
     * @param Node[] $nodes
     */
    public function afterTraverse(array $nodes): ?array
    {
        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $this->outputCount['overall'],
            MetricKey::OVERALL_OUTPUT_STATEMENTS
        );

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            $this->outputCount['file'],
            MetricKey::OUTPUT_COUNT
        );

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            new FunctionNameCollection($this->functions),
            'functions'
        );

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            new ClassNameCollection($this->classes),
            'classes'
        );

        return null;
    }

    /**
     * Count output statements.
     */
    private function countOutput(): void
    {
        ++$this->outputCount['overall'];
        ++$this->outputCount['file'];

        if ($this->inClass) {
            ++$this->outputCount['classes'];

            if ($this->inFunction) {
                ++$this->outputCount['methods'];
            }
        } elseif ($this->inFunction) {
            ++$this->outputCount['functions'];
        }
    }

    private function setFunctionMetrics(Node\Stmt\Function_ $node): void
    {
        $namespace = str_replace((string) $node->name, '', (string) $node->namespacedName);
        $namespace = rtrim($namespace, '\\');

        $identifierData = [
            'path' => $this->path,
            'name' => (string) $node->namespacedName,
        ];

        $fnMetricCollection = $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData
        );

        $metricData = [
            MetricKey::SINGLE_NAME => (string) $node->name,
            MetricKey::NAMESPACE => $namespace,
        ];

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FunctionCollection,
            $identifierData,
            $metricData
        );

        $this->parameterAnalyzer->handleParameters($node, $identifierData);
        $this->parameterAnalyzer->handleReturn($node, $identifierData);

        $this->metricsController->changeMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            MetricKey::OVERALL_FUNCTION_COUNT,
            'PhpCodeArch\incrementOr1IfNull'
        );

        $this->metricsController->setCollectionData(
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

        $classMetricCollection = $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData
        );

        $classId = (string) $classMetricCollection->getIdentifier();

        $classInfo = [
            'id' => $classId,
            'name' => $className,
            'namespace' => $namespace,
            'singleName' => $singleName,
        ];

        $classMetricsData = [
            MetricKey::INTERFACE => false,
            MetricKey::TRAIT => false,
            MetricKey::ABSTRACT => false,
            MetricKey::ENUM => false,
            MetricKey::FINAL => false,
            MetricKey::REAL_CLASS => false,
            MetricKey::ANONYMOUS => str_starts_with($className, 'anonymous@'),
            MetricKey::SINGLE_NAME => $singleName,
            MetricKey::NAMESPACE => $namespace,
        ];

        if (method_exists($node, 'isFinal') && $node->isFinal()) {
            $classMetricsData[MetricKey::FINAL] = true;
        }

        if (method_exists($node, 'isAbstract') && $node->isAbstract()) {
            $this->metricsController->changeMetricValue(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                MetricKey::OVERALL_ABSTRACT_CLASSES,
                'PhpCodeArch\incrementOr1IfNull'
            );

            $classMetricsData[MetricKey::ABSTRACT] = true;
        }

        switch (true) {
            case $node instanceof Node\Stmt\Class_:
                $this->metricsController->changeMetricValue(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    MetricKey::OVERALL_CLASSES,
                    function (int|float|null $value): int|float {
                        if (!$value) {
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

                $classMetricsData[MetricKey::REAL_CLASS] = true;
                break;

            case $node instanceof Node\Stmt\Enum_:
                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'enums',
                    $classId,
                    $className
                );

                $classMetricsData[MetricKey::ENUM] = true;
                break;

            case $node instanceof Node\Stmt\Interface_:
                $this->metricsController->changeMetricValue(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    MetricKey::OVERALL_INTERFACES,
                    'PhpCodeArch\incrementOr1IfNull'
                );

                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'interfaces',
                    $classId,
                    $className
                );

                $classMetricsData[MetricKey::INTERFACE] = true;
                $classMetricsData[MetricKey::ABSTRACT] = true;
                break;

            default:
                $this->metricsController->setCollectionData(
                    MetricCollectionTypeEnum::ProjectCollection,
                    null,
                    'traits',
                    $classId,
                    $className
                );

                $classMetricsData[MetricKey::TRAIT] = true;
                break;
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            $identifierData,
            $classMetricsData
        );

        $this->classMemberAnalyzer->handleClassMethods($classInfo, $node, $identifierData);
        $this->classMemberAnalyzer->handleClassConstants($classInfo, $node, $identifierData);
        $this->classMemberAnalyzer->handleClassProperties($classInfo, $node, $identifierData);

        $this->classes[$classId] = $className;
    }
}
