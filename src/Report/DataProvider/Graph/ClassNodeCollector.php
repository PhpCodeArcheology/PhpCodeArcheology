<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider\Graph;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

class ClassNodeCollector
{
    /** @var list<array<string, mixed>> */
    private array $nodes = [];
    /** @var list<array<string, mixed>> */
    private array $edges = [];
    /** @var list<array<string, mixed>> */
    private array $rawCycles = [];
    /** @var array<string, true> */
    private array $knownMethodIds = [];

    public function __construct(
        private readonly MetricsReaderInterface $reader,
    ) {
    }

    /**
     * @param array<string, string>                                                                     $nameToId
     * @param array<string, array{gitAuthors: array<mixed>, gitChurnCount: int, gitCodeAgeDays: mixed}> $fileGitData
     */
    public function processCollection(
        ClassMetricsCollection $collection,
        string $identifierString,
        array $nameToId,
        array $fileGitData,
    ): void {
        $className = $collection->getName();

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
        $methodsCollection = $this->reader->getCollectionByIdentifierString($identifierString, 'methods');
        if ($methodsCollection instanceof CollectionInterface) {
            foreach ($methodsCollection->getAsArray() as $methodId => $methodName) {
                if ('' === (string) $methodId || null === $methodName) {
                    continue;
                }
                $this->processMethodNode((string) $methodId, $classNodeId);
            }
        }

        // extends edges
        $extendsCollection = $this->reader->getCollectionByIdentifierString($identifierString, 'extends');
        if ($extendsCollection instanceof CollectionInterface) {
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
        $interfacesCollection = $this->reader->getCollectionByIdentifierString($identifierString, 'interfaces');
        if ($interfacesCollection instanceof CollectionInterface) {
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
        $traitsCollection = $this->reader->getCollectionByIdentifierString($identifierString, 'traits');
        if ($traitsCollection instanceof CollectionInterface) {
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

        // depends_on edges
        $usedClassesCollection = $this->reader->getCollectionByIdentifierString($identifierString, 'usedClasses');
        if ($usedClassesCollection instanceof CollectionInterface) {
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

        // cycle_member edges
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

            $this->rawCycles[] = [
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
    public function getRawCycles(): array
    {
        return $this->rawCycles;
    }

    /** @return array<string, true> */
    public function getKnownMethodIds(): array
    {
        return $this->knownMethodIds;
    }

    private function processMethodNode(string $methodId, string $classNodeId): void
    {
        $methodCollection = $this->reader->getMetricCollectionByIdentifierString($methodId);

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

        $this->edges[] = [
            'source' => $classNodeId,
            'target' => 'method:'.$methodId,
            'type' => 'declares',
            'weight' => 1,
        ];

        $this->knownMethodIds[$methodId] = true;
    }
}
