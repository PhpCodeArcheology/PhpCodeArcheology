<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Report\DataProvider\Graph\ClassNodeCollector;
use PhpCodeArch\Report\DataProvider\Graph\EdgeCollector;
use PhpCodeArch\Report\DataProvider\Graph\PackageNodeCollector;

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

    public function gatherData(): void
    {
        // Step 1: Build name→identifierString map for all class-like types
        /** @var array<string, string> $nameToId */
        $nameToId = [];
        foreach (['classes', 'interfaces', 'traits', 'enums'] as $key) {
            $collection = $this->reader->getCollection(
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

        // Step 2: Collect git data from file collections
        /** @var array<string, array{commitCount: int, filesChanged: int}> $authorData */
        $authorData = [];
        /** @var array<string, array{gitAuthors: array<mixed>, gitChurnCount: int, gitCodeAgeDays: mixed}> $fileGitData */
        $fileGitData = [];

        foreach ($this->registry->getAllCollections() as $collection) {
            if ($collection instanceof FileMetricsCollection) {
                $this->collectFileGitData($collection, $authorData, $fileGitData);
            }
        }

        // Step 3: Collect class and function nodes/edges
        $classCollector = new ClassNodeCollector($this->reader);

        foreach ($this->registry->getAllCollections() as $collection) {
            $identifierString = (string) $collection->getIdentifier();

            if ($collection instanceof ClassMetricsCollection) {
                $classCollector->processCollection($collection, $identifierString, $nameToId, $fileGitData);
            } elseif ($collection instanceof FunctionMetricsCollection) {
                $this->processFunctionCollection($collection, $identifierString);
            }
        }

        $this->nodes = array_merge($this->nodes, $classCollector->getNodes());
        $this->edges = array_merge($this->edges, $classCollector->getEdges());

        // Step 4: Author nodes
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

        // Step 5: Package nodes and clusters
        $packageCollector = new PackageNodeCollector($this->reader);
        $packageData = $packageCollector->collect($nameToId);
        $this->nodes = array_merge($this->nodes, $packageData['nodes']);
        $this->clusters = $packageData['clusters'];

        // Step 6: Deduplicate cycles
        $this->cycles = $this->deduplicateCycles($classCollector->getRawCycles());

        // Step 7: Build method call edges
        $edgeCollector = new EdgeCollector($this->reader);
        $this->edges = array_merge(
            $this->edges,
            $edgeCollector->buildMethodCallEdges($classCollector->getKnownMethodIds(), $nameToId)
        );

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

    private function processFunctionCollection(
        FunctionMetricsCollection $collection,
        string $identifierString,
    ): void {
        $functionType = $collection->getString(MetricKey::FUNCTION_TYPE);

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
}
