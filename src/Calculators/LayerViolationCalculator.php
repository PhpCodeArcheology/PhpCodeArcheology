<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class LayerViolationCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    // Default layer order (higher index = higher layer, lower layers should not depend on higher)
    // Convention-based: detected from namespace segments
    private const LAYER_ORDER = [
        'model' => 0,
        'entity' => 0,
        'domain' => 0,
        'repository' => 1,
        'persistence' => 1,
        'service' => 2,
        'application' => 2,
        'handler' => 2,
        'controller' => 3,
        'command' => 3,
        'console' => 3,
        'api' => 3,
    ];

    /** @var array<string, string> classId → detected layer name */
    private array $classLayers = [];

    /** @var array<string, string> className → classId */
    private array $nameToId = [];

    public function beforeTraverse(): void
    {
        $this->classLayers = [];
        $this->nameToId = [];

        $collections = ['classes', 'interfaces', 'traits', 'enums'];
        foreach ($collections as $collectionKey) {
            $items = $this->reader->getCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                $collectionKey
            );
            if (!$items instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                continue;
            }
            foreach ($items->getAsArray() as $id => $name) {
                if (!is_string($id) || !is_string($name)) {
                    continue;
                }
                $this->nameToId[$name] = $id;
            }
        }
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();
        $namespace = $metrics->getString(MetricKey::NAMESPACE);
        $layer = $this->detectLayer($namespace);
        $this->classLayers[$identifierString] = $layer;
    }

    public function afterTraverse(): void
    {
        foreach ($this->classLayers as $classId => $layer) {
            if ('' === $layer) {
                $this->writer->setMetricValuesByIdentifierString(
                    $classId,
                    [MetricKey::LAYER_VIOLATION_COUNT => 0, MetricKey::LAYER_VIOLATIONS => []]
                );
                continue;
            }

            $layerIndex = self::LAYER_ORDER[$layer] ?? -1;
            if ($layerIndex < 0) {
                $this->writer->setMetricValuesByIdentifierString(
                    $classId,
                    [MetricKey::LAYER_VIOLATION_COUNT => 0, MetricKey::LAYER_VIOLATIONS => []]
                );
                continue;
            }

            // Check dependencies
            $usedClasses = $this->reader->getCollectionByIdentifierString($classId, 'usedClasses');
            $violations = [];

            if ($usedClasses instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
                foreach ($usedClasses->getAsArray() as $usedClassName) {
                    if (!is_string($usedClassName)) {
                        continue;
                    }
                    $usedId = $this->nameToId[$usedClassName] ?? null;
                    if (null === $usedId || !isset($this->classLayers[$usedId])) {
                        continue;
                    }

                    $usedLayer = $this->classLayers[$usedId];
                    $usedLayerIndex = self::LAYER_ORDER[$usedLayer] ?? -1;

                    // Lower layer depending on higher layer = violation
                    if ($usedLayerIndex > $layerIndex) {
                        $usedShortName = array_search($usedId, $this->nameToId) ?: $usedId;
                        $violations[] = "{$layer} → {$usedLayer} ({$usedShortName})";
                    }
                }
            }

            $this->writer->setMetricValuesByIdentifierString(
                $classId,
                [
                    MetricKey::LAYER_VIOLATION_COUNT => count($violations),
                    MetricKey::LAYER_VIOLATIONS => $violations,
                ]
            );
        }
    }

    private function detectLayer(string $namespace): string
    {
        $parts = explode('\\', strtolower($namespace));
        foreach (array_reverse($parts) as $part) {
            if (isset(self::LAYER_ORDER[$part])) {
                return $part;
            }
        }

        return '';
    }
}
