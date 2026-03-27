<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class GraphDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    /** @var list<array<string, mixed>> */
    private array $nodes = [];
    /** @var list<array<string, mixed>> */
    private array $edges = [];
    /** @var list<array<string, mixed>> */
    private array $clusters = [];
    /** @var list<array<string, mixed>> */
    private array $cycles = [];
    /** @var array<string, true> */
    private array $knownMethodIds = [];

    public function gatherData(): void
    {
        // Step 1: Build name→identifierString map for all class-like types
        /** @var array<string, string> $nameToId */
        $nameToId = [];
        foreach (['classes', 'interfaces', 'traits', 'enums'] as $key) {
            $collection = $this->metricsController->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $key
            );
            if (!$collection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                continue;
            }
            foreach ($collection->getAsArray() as $id => $name) {
                if (is_string($name)) {
                    $nameToId[$name] = (string) $id;
                }
            }
        }

        // Step 2: First pass — collect git data from files and aggregate authors
        /** @var array<string, array{commitCount: int, filesChanged: int}> $authorData */
        $authorData = [];
        /** @var array<string, array{gitAuthors: array<mixed>, gitChurnCount: int, gitCodeAgeDays: mixed}> $fileGitData */
        $fileGitData = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if ($collection instanceof FileMetricsCollection) {
                $this->collectFileGitData($collection, $authorData, $fileGitData);
            }
        }

        // Step 3: Second pass — collect class, function nodes and edges
        /** @var list<array<string, mixed>> $rawCycles */
        $rawCycles = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            $identifierString = (string) $collection->getIdentifier();

            if ($collection instanceof ClassMetricsCollection) {
                $this->processClassCollection($collection, $identifierString, $nameToId, $rawCycles, $fileGitData);
            } elseif ($collection instanceof FunctionMetricsCollection) {
                $this->processFunctionCollection($collection, $identifierString);
            }
        }

        // Step 3: Author nodes
        foreach ($authorData as $authorName => $data) {
            $this->nodes[] = [
                'id' => 'author:'.$authorName,
                'type' => 'author',
                'name' => $authorName,
                'metrics' => [
                    'commitCount' => $data['commitCount'],
                    'filesChanged' => $data['filesChanged'],
                ],
                'problems' => [],
            ];
        }

        // Step 4: Package nodes and clusters
        $packages = $this->metricsController->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'packages'
        );

        foreach ($packages as $packageCollection) {
            $packageName = $packageCollection->getName();
            $packageNodeId = 'package:'.$packageName;

            $this->nodes[] = [
                'id' => $packageNodeId,
                'type' => 'package',
                'name' => $packageName,
                'metrics' => [
                    'abstractness' => $packageCollection->get(MetricKey::ABSTRACTNESS)?->getValue(),
                    'instability' => $packageCollection->get(MetricKey::INSTABILITY)?->getValue(),
                    'distanceFromMainline' => $packageCollection->get(MetricKey::DISTANCE_FROM_MAINLINE)?->getValue(),
                    'cohesion' => null,
                ],
                'problems' => [],
            ];

            $clusterNodeIds = [];
            $classesInPackage = $packageCollection->getCollection('classes');
            if (null !== $classesInPackage) {
                foreach ($classesInPackage->getAsArray() as $className) {
                    if (!is_string($className)) {
                        continue;
                    }
                    $resolvedId = $nameToId[$className] ?? null;
                    if (null !== $resolvedId) {
                        $clusterNodeIds[] = 'class:'.$resolvedId;
                    }
                }
            }

            if ([] !== $clusterNodeIds) {
                $this->clusters[] = [
                    'id' => 'package:'.$packageName,
                    'name' => $packageName,
                    'nodeIds' => $clusterNodeIds,
                ];
            }
        }

        // Step 5: Deduplicate cycles (each cycle is reported once per member class)
        $this->cycles = $this->deduplicateCycles($rawCycles);

        // Step 6: Build method calls edges (requires all method nodes to be known)
        $this->buildMethodCallEdges($nameToId);

        $this->templateData['graphData'] = $this->getGraphData();
    }

    /** @return list<array<string, mixed>> */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /** @return list<array<string, mixed>> */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /** @return list<array<string, mixed>> */
    public function getClusters(): array
    {
        return $this->clusters;
    }

    /** @return list<array<string, mixed>> */
    public function getCycles(): array
    {
        return $this->cycles;
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, clusters: list<array<string, mixed>>, cycles: list<array<string, mixed>>}
     */
    public function getGraphData(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'clusters' => $this->clusters,
            'cycles' => $this->cycles,
        ];
    }

    /**
     * @param array<string, array{commitCount: int, filesChanged: int}>                                 $authorData
     * @param array<string, array{gitAuthors: array<mixed>, gitChurnCount: int, gitCodeAgeDays: mixed}> $fileGitData
     */
    private function collectFileGitData(
        FileMetricsCollection $collection,
        array &$authorData,
        array &$fileGitData,
    ): void {
        $path = $collection->getPath();
        $gitAuthors = $collection->getArray(MetricKey::GIT_AUTHORS);
        $churnCount = $collection->getInt(MetricKey::GIT_CHURN_COUNT);
        $codeAgeDays = $collection->get(MetricKey::GIT_CODE_AGE_DAYS)?->getValue();

        $fileGitData[$path] = [
            'gitAuthors' => $gitAuthors,
            'gitChurnCount' => $churnCount,
            'gitCodeAgeDays' => $codeAgeDays,
        ];

        foreach ($gitAuthors as $authorName) {
            if (!is_string($authorName)) {
                continue;
            }
            if (!isset($authorData[$authorName])) {
                $authorData[$authorName] = ['commitCount' => 0, 'filesChanged' => 0];
            }
            ++$authorData[$authorName]['filesChanged'];
            $authorData[$authorName]['commitCount'] += $churnCount;
        }
    }

    /**
     * @param array<string, string>                                                                     $nameToId
     * @param list<array<string, mixed>>                                                                $rawCycles
     * @param array<string, array{gitAuthors: array<mixed>, gitChurnCount: int, gitCodeAgeDays: mixed}> $fileGitData
     */
    private function processClassCollection(
        ClassMetricsCollection $collection,
        string $identifierString,
        array $nameToId,
        array &$rawCycles,
        array $fileGitData,
    ): void {
        $className = $collection->getName();

        // Skip anonymous classes
        if (str_starts_with($className, 'anonymous@')) {
            return;
        }

        $classNodeId = 'class:'.$identifierString;
        $filePath = $collection->getPath();
        $gitData = $fileGitData[$filePath] ?? null;

        $metrics = [
            'cc' => $collection->get(MetricKey::CC)?->getValue(),
            'lcom' => $collection->get(MetricKey::LCOM)?->getValue(),
            'mi' => $collection->get(MetricKey::MAINTAINABILITY_INDEX)?->getValue(),
            'instability' => $collection->get(MetricKey::INSTABILITY)?->getValue(),
            'afferentCoupling' => $collection->get(MetricKey::USED_BY_COUNT)?->getValue(),
            'efferentCoupling' => $collection->get(MetricKey::USES_COUNT)?->getValue(),
        ];

        if (null !== $gitData) {
            $metrics['gitChurnCount'] = $gitData['gitChurnCount'];
            $metrics['gitCodeAgeDays'] = $gitData['gitCodeAgeDays'];
        }

        $this->nodes[] = [
            'id' => $classNodeId,
            'type' => 'class',
            'name' => $className,
            'path' => $filePath,
            'metrics' => $metrics,
            'flags' => [
                'interface' => $collection->getBool(MetricKey::INTERFACE),
                'trait' => $collection->getBool(MetricKey::TRAIT),
                'abstract' => $collection->getBool(MetricKey::ABSTRACT),
                'final' => $collection->getBool(MetricKey::FINAL),
                'enum' => $collection->getBool(MetricKey::ENUM),
            ],
            'problems' => [],
        ];

        // authored_by edges: Class→Author
        if (null !== $gitData) {
            foreach ($gitData['gitAuthors'] as $authorName) {
                if (!is_string($authorName)) {
                    continue;
                }
                $this->edges[] = [
                    'source' => $classNodeId,
                    'target' => 'author:'.$authorName,
                    'type' => 'authored_by',
                    'weight' => 1,
                ];
            }
        }

        // declares edges: Class→Method
        $methodsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'methods');
        if ($methodsCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($methodsCollection->getAsArray() as $methodId => $methodName) {
                if ('' === (string) $methodId || null === $methodName) {
                    continue;
                }
                $this->processMethodNode((string) $methodId, $classNodeId);
            }
        }

        // extends edges
        $extendsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'extends');
        if ($extendsCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($extendsCollection->getAsArray() as $parentName) {
                if (!is_string($parentName) || '' === $parentName) {
                    continue;
                }
                $parentId = $nameToId[$parentName] ?? null;
                if (null !== $parentId) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:'.$parentId,
                        'type' => 'extends',
                        'weight' => 1,
                    ];
                }
            }
        }

        // implements edges
        $interfacesCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'interfaces');
        if ($interfacesCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($interfacesCollection->getAsArray() as $interfaceName) {
                if (!is_string($interfaceName) || '' === $interfaceName) {
                    continue;
                }
                $interfaceId = $nameToId[$interfaceName] ?? null;
                if (null !== $interfaceId) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:'.$interfaceId,
                        'type' => 'implements',
                        'weight' => 1,
                    ];
                }
            }
        }

        // uses_trait edges
        $traitsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'traits');
        if ($traitsCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($traitsCollection->getAsArray() as $traitName) {
                if (!is_string($traitName) || '' === $traitName) {
                    continue;
                }
                $traitId = $nameToId[$traitName] ?? null;
                if (null !== $traitId) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:'.$traitId,
                        'type' => 'uses_trait',
                        'weight' => 1,
                    ];
                }
            }
        }

        // depends_on edges — use usedClasses (NOT usesInProject which includes extends/implements)
        $usedClassesCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'usedClasses');
        if ($usedClassesCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($usedClassesCollection->getAsArray() as $depName) {
                if (!is_string($depName) || '' === $depName) {
                    continue;
                }
                $depId = $nameToId[$depName] ?? null;
                if (null !== $depId) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:'.$depId,
                        'type' => 'depends_on',
                        'weight' => 1,
                    ];
                }
            }
        }

        // belongs_to edge: Class→Package
        $packageName = $collection->get(MetricKey::PACKAGE)?->getValue();
        if (is_string($packageName) && '' !== $packageName) {
            $this->edges[] = [
                'source' => $classNodeId,
                'target' => 'package:'.$packageName,
                'type' => 'belongs_to',
                'weight' => 1,
            ];
        }

        // cycle_member edges (bidirectional — each class adds edges to all other members)
        if ($collection->getBool(MetricKey::IN_DEPENDENCY_CYCLE)) {
            $cycleClassNames = $collection->getArray(MetricKey::DEPENDENCY_CYCLE_CLASSES);
            $cycleLength = $collection->get(MetricKey::DEPENDENCY_CYCLE_LENGTH)?->getValue();

            foreach ($cycleClassNames as $memberName) {
                if (!is_string($memberName) || $memberName === $className) {
                    continue;
                }
                $memberId = $nameToId[$memberName] ?? null;
                if (null !== $memberId) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:'.$memberId,
                        'type' => 'cycle_member',
                        'weight' => 1,
                    ];
                }
            }

            /** @var list<string> $stringNames */
            $stringNames = array_filter(
                $cycleClassNames,
                fn (mixed $n): bool => is_string($n)
            );

            $rawCycles[] = [
                'nodes' => array_values(array_filter(
                    array_map(
                        static fn (string $name) => isset($nameToId[$name]) ? 'class:'.$nameToId[$name] : null,
                        $stringNames
                    )
                )),
                'length' => $cycleLength,
            ];
        }
    }

    private function processFunctionCollection(
        FunctionMetricsCollection $collection,
        string $identifierString,
    ): void {
        $functionType = $collection->getString(MetricKey::FUNCTION_TYPE);

        // Skip methods — they are handled via processClassCollection declares edges
        if ('method' === $functionType) {
            return;
        }

        $functionName = $collection->getName();

        $cogC = $collection->get(MetricKey::COGNITIVE_COMPLEXITY)?->getValue()
            ?? $collection->get('cogC')?->getValue();

        $this->nodes[] = [
            'id' => 'function:'.$identifierString,
            'type' => 'function',
            'name' => $functionName,
            'metrics' => [
                'cc' => $collection->get(MetricKey::CC)?->getValue(),
                'cognitiveComplexity' => $cogC,
                'params' => $collection->get(MetricKey::PARAMETER_COUNT)?->getValue(),
            ],
            'problems' => [],
        ];
    }

    private function processMethodNode(
        string $methodId,
        string $classNodeId,
    ): void {
        $methodCollection = $this->metricsController->getMetricCollectionByIdentifierString($methodId);

        $methodName = $methodCollection->getName();

        $cogC = $methodCollection->get(MetricKey::COGNITIVE_COMPLEXITY)?->getValue()
            ?? $methodCollection->get('cogC')?->getValue();

        $this->nodes[] = [
            'id' => 'method:'.$methodId,
            'type' => 'method',
            'name' => $methodName,
            'metrics' => [
                'cc' => $methodCollection->get(MetricKey::CC)?->getValue(),
                'cognitiveComplexity' => $cogC,
                'params' => $methodCollection->get(MetricKey::PARAMETER_COUNT)?->getValue(),
            ],
            'flags' => [
                'public' => $methodCollection->getBool(MetricKey::PUBLIC),
                'private' => $methodCollection->getBool(MetricKey::PRIVATE),
                'protected' => $methodCollection->getBool(MetricKey::PROTECTED),
                'static' => $methodCollection->getBool(MetricKey::STATIC),
            ],
            'problems' => [],
        ];

        // declares edge: Class→Method
        $this->edges[] = [
            'source' => $classNodeId,
            'target' => 'method:'.$methodId,
            'type' => 'declares',
            'weight' => 1,
        ];

        $this->knownMethodIds[$methodId] = true;
    }

    /**
     * @param list<array<string, mixed>> $rawCycles
     *
     * @return list<array<string, mixed>>
     */
    private function deduplicateCycles(array $rawCycles): array
    {
        $seen = [];
        $result = [];

        foreach ($rawCycles as $cycle) {
            $nodesRaw = $cycle['nodes'] ?? [];
            $sortedNodes = is_array($nodesRaw) ? $nodesRaw : [];
            sort($sortedNodes);
            $key = implode(',', array_map(fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $sortedNodes));

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $cycle;
            }
        }

        return $result;
    }

    /** @param array<string, string> $nameToId */
    private function buildMethodCallEdges(array $nameToId): void
    {
        foreach (array_keys($this->knownMethodIds) as $methodId) {
            $methodCallsCollection = $this->metricsController->getCollectionByIdentifierString($methodId, 'methodCalls');
            if (!$methodCallsCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                continue;
            }

            /** @var array<string, int> $callCounts */
            $callCounts = [];
            foreach ($methodCallsCollection->getAsArray() as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $targetClass = is_string($call['targetClass'] ?? null) ? $call['targetClass'] : '';
                $targetMethod = is_string($call['targetMethod'] ?? null) ? $call['targetMethod'] : '';
                if ('' === $targetClass || '' === $targetMethod) {
                    continue;
                }
                $callKey = $targetClass.'::'.$targetMethod;
                $callCounts[$callKey] = ($callCounts[$callKey] ?? 0) + 1;
            }

            foreach ($callCounts as $callKey => $count) {
                [$targetClassName, $targetMethodName] = explode('::', $callKey, 2);

                if (!isset($nameToId[$targetClassName])) {
                    continue;
                }

                $targetMethodId = (string) FunctionAndClassIdentifier::ofNameAndPath(
                    $targetMethodName,
                    $targetClassName
                );

                if (!isset($this->knownMethodIds[$targetMethodId])) {
                    continue;
                }

                $this->edges[] = [
                    'source' => 'method:'.$methodId,
                    'target' => 'method:'.$targetMethodId,
                    'type' => 'calls',
                    'weight' => $count,
                ];
            }
        }
    }
}
