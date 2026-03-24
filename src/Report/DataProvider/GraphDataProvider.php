<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class GraphDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $nodes = [];
    private array $edges = [];
    private array $clusters = [];
    private array $cycles = [];

    public function gatherData(): void
    {
        // Step 1: Build name→identifierString map for all class-like types
        $nameToId = [];
        foreach (['classes', 'interfaces', 'traits', 'enums'] as $key) {
            $collection = $this->metricsController->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $key
            );
            if ($collection === null) {
                continue;
            }
            foreach ($collection->getAsArray() as $id => $name) {
                $nameToId[$name] = $id;
            }
        }

        // Step 2: First pass — collect git data from files and aggregate authors
        $authorData = [];
        $fileGitData = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if ($collection instanceof FileMetricsCollection) {
                $this->collectFileGitData($collection, $authorData, $fileGitData);
            }
        }

        // Step 3: Second pass — collect class, function nodes and edges
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
                'id' => 'author:' . $authorName,
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
            $packageNodeId = 'package:' . $packageName;

            $this->nodes[] = [
                'id' => $packageNodeId,
                'type' => 'package',
                'name' => $packageName,
                'metrics' => [
                    'abstractness' => $packageCollection->get('abstractness')?->getValue(),
                    'instability' => $packageCollection->get('instability')?->getValue(),
                    'distanceFromMainline' => $packageCollection->get('distanceFromMainline')?->getValue(),
                    'cohesion' => null,
                ],
                'problems' => [],
            ];

            $clusterNodeIds = [];
            $classesInPackage = $packageCollection->getCollection('classes');
            if ($classesInPackage !== null) {
                foreach ($classesInPackage->getAsArray() as $className) {
                    $resolvedId = $nameToId[$className] ?? null;
                    if ($resolvedId !== null) {
                        $clusterNodeIds[] = 'class:' . $resolvedId;
                    }
                }
            }

            if (!empty($clusterNodeIds)) {
                $this->clusters[] = [
                    'id' => 'package:' . $packageName,
                    'name' => $packageName,
                    'nodeIds' => $clusterNodeIds,
                ];
            }
        }

        // Step 5: Deduplicate cycles (each cycle is reported once per member class)
        $this->cycles = $this->deduplicateCycles($rawCycles);

        $this->templateData['graphData'] = $this->getGraphData();
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getEdges(): array
    {
        return $this->edges;
    }

    public function getClusters(): array
    {
        return $this->clusters;
    }

    public function getCycles(): array
    {
        return $this->cycles;
    }

    public function getGraphData(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'clusters' => $this->clusters,
            'cycles' => $this->cycles,
        ];
    }

    private function collectFileGitData(
        FileMetricsCollection $collection,
        array &$authorData,
        array &$fileGitData,
    ): void {
        $path = $collection->getPath();
        $gitAuthors = $collection->get('gitAuthors')?->getValue() ?? [];
        $churnCount = $collection->get('gitChurnCount')?->getValue() ?? 0;
        $codeAgeDays = $collection->get('gitCodeAgeDays')?->getValue();

        $fileGitData[$path] = [
            'gitAuthors' => $gitAuthors,
            'gitChurnCount' => $churnCount,
            'gitCodeAgeDays' => $codeAgeDays,
        ];

        foreach ($gitAuthors as $authorName) {
            if (!isset($authorData[$authorName])) {
                $authorData[$authorName] = ['commitCount' => 0, 'filesChanged' => 0];
            }
            $authorData[$authorName]['filesChanged']++;
            $authorData[$authorName]['commitCount'] += $churnCount;
        }
    }

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

        $classNodeId = 'class:' . $identifierString;
        $filePath = $collection->getPath();
        $gitData = $fileGitData[$filePath] ?? null;

        $metrics = [
            'cc' => $collection->get('cc')?->getValue(),
            'lcom' => $collection->get('lcom')?->getValue(),
            'mi' => $collection->get('maintainabilityIndex')?->getValue(),
            'instability' => $collection->get('instability')?->getValue(),
            'afferentCoupling' => $collection->get('usedByCount')?->getValue(),
            'efferentCoupling' => $collection->get('usesCount')?->getValue(),
        ];

        if ($gitData !== null) {
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
                'interface' => $collection->get('interface')?->getValue() ?? false,
                'trait' => $collection->get('trait')?->getValue() ?? false,
                'abstract' => $collection->get('abstract')?->getValue() ?? false,
                'final' => $collection->get('final')?->getValue() ?? false,
                'enum' => $collection->get('enum')?->getValue() ?? false,
            ],
            'problems' => [],
        ];

        // authored_by edges: Class→Author
        if ($gitData !== null) {
            foreach ($gitData['gitAuthors'] as $authorName) {
                $this->edges[] = [
                    'source' => $classNodeId,
                    'target' => 'author:' . $authorName,
                    'type' => 'authored_by',
                    'weight' => 1,
                ];
            }
        }

        // declares edges: Class→Method
        $methodsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'methods');
        if ($methodsCollection !== null) {
            foreach ($methodsCollection->getAsArray() as $methodId => $methodName) {
                if ($methodId === null || $methodName === null) continue;
                $this->processMethodNode((string) $methodId, $classNodeId);
            }
        }

        // extends edges
        $extendsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'extends');
        if ($extendsCollection !== null) {
            foreach ($extendsCollection->getAsArray() as $parentName) {
                if ($parentName === null || $parentName === '') continue;
                $parentId = $nameToId[$parentName] ?? null;
                if ($parentId !== null) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:' . $parentId,
                        'type' => 'extends',
                        'weight' => 1,
                    ];
                }
            }
        }

        // implements edges
        $interfacesCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'interfaces');
        if ($interfacesCollection !== null) {
            foreach ($interfacesCollection->getAsArray() as $interfaceName) {
                if ($interfaceName === null || $interfaceName === '') continue;
                $interfaceId = $nameToId[$interfaceName] ?? null;
                if ($interfaceId !== null) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:' . $interfaceId,
                        'type' => 'implements',
                        'weight' => 1,
                    ];
                }
            }
        }

        // uses_trait edges
        $traitsCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'traits');
        if ($traitsCollection !== null) {
            foreach ($traitsCollection->getAsArray() as $traitName) {
                if ($traitName === null || $traitName === '') continue;
                $traitId = $nameToId[$traitName] ?? null;
                if ($traitId !== null) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:' . $traitId,
                        'type' => 'uses_trait',
                        'weight' => 1,
                    ];
                }
            }
        }

        // depends_on edges — use usedClasses (NOT usesInProject which includes extends/implements)
        $usedClassesCollection = $this->metricsController->getCollectionByIdentifierString($identifierString, 'usedClasses');
        if ($usedClassesCollection !== null) {
            foreach ($usedClassesCollection->getAsArray() as $depName) {
                if ($depName === null || $depName === '') continue;
                $depId = $nameToId[$depName] ?? null;
                if ($depId !== null) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:' . $depId,
                        'type' => 'depends_on',
                        'weight' => 1,
                    ];
                }
            }
        }

        // belongs_to edge: Class→Package
        $packageName = $collection->get('package')?->getValue();
        if ($packageName !== null && $packageName !== '') {
            $this->edges[] = [
                'source' => $classNodeId,
                'target' => 'package:' . $packageName,
                'type' => 'belongs_to',
                'weight' => 1,
            ];
        }

        // cycle_member edges (bidirectional — each class adds edges to all other members)
        $inCycle = $collection->get('inDependencyCycle')?->getValue();
        if ($inCycle === true) {
            $cycleClassNames = $collection->get('dependencyCycleClasses')?->getValue() ?? [];
            $cycleLength = $collection->get('dependencyCycleLength')?->getValue();

            foreach ($cycleClassNames as $memberName) {
                if ($memberName === $className) {
                    continue;
                }
                $memberId = $nameToId[$memberName] ?? null;
                if ($memberId !== null) {
                    $this->edges[] = [
                        'source' => $classNodeId,
                        'target' => 'class:' . $memberId,
                        'type' => 'cycle_member',
                        'weight' => 1,
                    ];
                }
            }

            $rawCycles[] = [
                'nodes' => array_values(array_filter(
                    array_map(
                        static fn(string $name) => isset($nameToId[$name]) ? 'class:' . $nameToId[$name] : null,
                        $cycleClassNames
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
        $functionType = $collection->get('functionType')?->getValue();

        // Skip methods — they are handled via processClassCollection declares edges
        if ($functionType === 'method') {
            return;
        }

        $functionName = $collection->getName();

        $cogC = $collection->get('cognitiveComplexity')?->getValue()
            ?? $collection->get('cogC')?->getValue();

        $this->nodes[] = [
            'id' => 'function:' . $identifierString,
            'type' => 'function',
            'name' => $functionName,
            'metrics' => [
                'cc' => $collection->get('cc')?->getValue(),
                'cognitiveComplexity' => $cogC,
                'params' => $collection->get('parameterCount')?->getValue(),
            ],
            'problems' => [],
        ];
    }

    private function processMethodNode(
        string $methodId,
        string $classNodeId,
    ): void {
        $methodCollection = $this->metricsController->getMetricCollectionByIdentifierString($methodId);
        if ($methodCollection === null) {
            return;
        }

        $methodName = $methodCollection->getName();

        $cogC = $methodCollection->get('cognitiveComplexity')?->getValue()
            ?? $methodCollection->get('cogC')?->getValue();

        $this->nodes[] = [
            'id' => 'method:' . $methodId,
            'type' => 'method',
            'name' => $methodName,
            'metrics' => [
                'cc' => $methodCollection->get('cc')?->getValue(),
                'cognitiveComplexity' => $cogC,
                'params' => $methodCollection->get('parameterCount')?->getValue(),
            ],
            'flags' => [
                'public' => $methodCollection->get('public')?->getValue() ?? false,
                'private' => $methodCollection->get('private')?->getValue() ?? false,
                'protected' => $methodCollection->get('protected')?->getValue() ?? false,
                'static' => $methodCollection->get('static')?->getValue() ?? false,
            ],
            'problems' => [],
        ];

        // declares edge: Class→Method
        $this->edges[] = [
            'source' => $classNodeId,
            'target' => 'method:' . $methodId,
            'type' => 'declares',
            'weight' => 1,
        ];
    }

    private function deduplicateCycles(array $rawCycles): array
    {
        $seen = [];
        $result = [];

        foreach ($rawCycles as $cycle) {
            $sortedNodes = $cycle['nodes'];
            sort($sortedNodes);
            $key = implode(',', $sortedNodes);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $cycle;
            }
        }

        return $result;
    }
}
