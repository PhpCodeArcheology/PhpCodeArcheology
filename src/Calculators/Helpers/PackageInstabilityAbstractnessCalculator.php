<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators\Helpers;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;

class PackageInstabilityAbstractnessCalculator
{
    /** @var array<string, array<string, int>> */
    private array $packages = [];

    /** @var array<string, array<string, string[]>> */
    private array $packagesMap = [
        'uses' => [],
        'usedBy' => [],
    ];

    /** @var array<string, string[]> */
    private array $abstractClasses = [];

    /** @var array<string, string[]> */
    private array $concreteClasses = [];

    private string $currentPackage = '';

    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsWriterInterface $writer,
    ) {
    }

    public function beforeTraverse(): void
    {
        $packagesCollection = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'packages'
        );

        if (null === $packagesCollection) {
            return;
        }

        $rawPackages = $packagesCollection->getAsArray();
        /** @var array<string, string> $stringPackages */
        $stringPackages = array_filter(
            $rawPackages,
            static fn (mixed $v, mixed $k): bool => is_string($k) && is_string($v),
            ARRAY_FILTER_USE_BOTH
        );

        $packages = array_flip($stringPackages);

        $this->packages = array_map(fn () => [
            MetricKey::USES_COUNT => 0,
            MetricKey::USED_BY_COUNT => 0,
        ], $packages);
    }

    public function afterTraverse(): void
    {
        foreach ($this->packages as $packageName => $packageData) {
            $this->writer->setMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $packageData
            );

            if (!isset($this->abstractClasses[$packageName]) || !isset($this->concreteClasses[$packageName])) {
                continue;
            }

            $packageMetrics = $this->reader->getMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                [
                    MetricKey::USES_COUNT,
                    MetricKey::USED_BY_COUNT,
                ],
            );

            $usesCount = $packageMetrics[MetricKey::USES_COUNT]?->asFloat() ?? 0.0;
            $usedByCount = $packageMetrics[MetricKey::USED_BY_COUNT]?->asFloat() ?? 0.0;

            $instability = ($usesCount + $usedByCount) > 0 ? $usesCount / ($usesCount + $usedByCount) : 0;
            $abstractClassCount = count($this->abstractClasses[$packageName]);
            $concreteClassCount = count($this->concreteClasses[$packageName]);
            $abstractness = ($abstractClassCount + $concreteClassCount) > 0 ?
                $abstractClassCount / ($abstractClassCount + $concreteClassCount)
                : 0;
            $distanceFromMainline = $abstractness + $instability - 1;

            $newPackageMetrics = [
                MetricKey::INSTABILITY => $instability,
                MetricKey::ABSTRACTNESS => $abstractness,
                MetricKey::DISTANCE_FROM_MAINLINE => $distanceFromMainline,
            ];

            $this->writer->setMetricValues(
                MetricCollectionTypeEnum::PackageCollection,
                ['name' => $packageName],
                $newPackageMetrics
            );
        }
    }

    /**
     * @return array{array<string, string[]>, array<string, string[]>}
     */
    public function handlePackage(string $identifierString, string $className): array
    {
        $classMetrics = $this->reader->getMetricValuesByIdentifierString(
            $identifierString,
            [MetricKey::PACKAGE, MetricKey::REAL_CLASS, MetricKey::ABSTRACT, MetricKey::INTERFACE]
        );

        $realClass = $classMetrics[MetricKey::REAL_CLASS]?->asBool() ?? false;
        $abstract = $classMetrics[MetricKey::ABSTRACT]?->asBool() ?? false;
        $interface = $classMetrics[MetricKey::INTERFACE]?->asBool() ?? false;
        $package = $classMetrics[MetricKey::PACKAGE]?->asString() ?? '';

        $this->currentPackage = $package;

        if (!isset($this->packagesMap['uses'][$package])) {
            $this->packagesMap['uses'][$package] = [];
        }
        if (!isset($this->packagesMap['usedBy'][$package])) {
            $this->packagesMap['usedBy'][$package] = [];
        }

        if (!isset($this->abstractClasses[$package])) {
            $this->abstractClasses[$package] = [];
        }

        if (!isset($this->concreteClasses[$package])) {
            $this->concreteClasses[$package] = [];
        }

        if (($realClass && $abstract || $interface) && !in_array($className, $this->abstractClasses[$package], true)) {
            $this->abstractClasses[$package][] = $className;
        } elseif ($realClass && !in_array($className, $this->abstractClasses[$package], true)) {
            $this->concreteClasses[$package][] = $className;
        }

        return [
            $this->abstractClasses,
            $this->concreteClasses,
        ];
    }

    public function handleDependency(string $dependency, string $identifierString, bool $isTrait): void
    {
        $usedByMetric = $this->reader->getMetricCollectionByIdentifierString($identifierString);
        $usedByPackage = $usedByMetric->getString(MetricKey::PACKAGE);

        if ($this->currentPackage !== $usedByPackage && !$isTrait) {
            if (!isset($this->packagesMap['uses'][$this->currentPackage])) {
                $this->packagesMap['uses'][$this->currentPackage] = [];
            }
            if (!isset($this->packagesMap['usedBy'][$this->currentPackage])) {
                $this->packagesMap['usedBy'][$this->currentPackage] = [];
            }

            if (!in_array($dependency, $this->packagesMap['uses'][$this->currentPackage], true)) {
                if (isset($this->packages[$this->currentPackage])) {
                    ++$this->packages[$this->currentPackage][MetricKey::USES_COUNT];
                }
                $this->packagesMap['uses'][$this->currentPackage][] = $dependency;
            }

            if (!in_array($dependency, $this->packagesMap['usedBy'][$this->currentPackage], true)) {
                if (isset($this->packages[$usedByPackage])) {
                    ++$this->packages[$usedByPackage][MetricKey::USED_BY_COUNT];
                }
                $this->packagesMap['usedBy'][$this->currentPackage][] = $dependency;
            }
        }
    }
}
