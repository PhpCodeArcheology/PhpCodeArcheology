<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class TestMappingCalculator implements CalculatorInterface
{
    /**
     * @param array<string, array{linerate: float, statements: int}>|null $coverageData
     */
    public function __construct(
        private readonly MetricsController $metricsController,
        private readonly ?FrameworkDetectionResult $frameworkDetection = null,
        private readonly ?TestScanResult $testScanResult = null,
        private readonly ?array $coverageData = null,
        private readonly ?string $projectRoot = null,
        private readonly ?TestCoversParseResult $coversParseResult = null,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        // All real work is done in afterTraverse()
    }

    public function afterTraverse(): void
    {
        if (!$this->testScanResult instanceof TestScanResult) {
            $this->setZeroProjectMetrics();

            return;
        }

        $totalTestFiles = count($this->testScanResult->classBasedTestFiles)
            + count($this->testScanResult->functionBasedTestFiles);

        if (0 === $totalTestFiles) {
            $this->setZeroProjectMetrics();

            return;
        }

        [$shortNameIndex, $fqcnIndex, $productionIds, $abstractIds] = $this->buildProductionIndex();

        /** @var array<string, int> identifierString → test file count */
        $classTestCount = [];
        /** @var array<string, string> identifierString → test type */
        $classTestType = [];

        foreach ($this->testScanResult->classBasedTestFiles as $testFile) {
            $identifierStrings = $this->resolveProductionClasses($testFile, $shortNameIndex, $fqcnIndex);
            $testType = $this->testScanResult->testFileToType[$testFile] ?? 'unknown';
            foreach ($identifierStrings as $id) {
                $classTestCount[$id] = ($classTestCount[$id] ?? 0) + 1;
                $classTestType[$id] = $this->bestTestType($classTestType[$id] ?? null, $testType);
            }
        }

        // Set per-class metrics for all production classes
        $testedCount = 0;
        $untestedCount = 0;
        $totalWeightedLinerate = 0.0;
        $totalStatements = 0;

        foreach ($productionIds as $id) {
            $hasMappedTest = isset($classTestCount[$id]);

            // Check Clover XML coverage: if a class has any line coverage, it was
            // executed during tests — a stronger signal than naming conventions
            $covEntry = null;
            $hasCoverage = false;
            if (null !== $this->coverageData && [] !== $this->coverageData) {
                $covEntry = $this->lookupCoverage($id);
                $hasCoverage = null !== $covEntry && $covEntry['linerate'] > 0;
            }

            $hasTest = $hasMappedTest || $hasCoverage;

            if ($hasTest) {
                ++$testedCount;
            } elseif (!isset($abstractIds[$id])) {
                ++$untestedCount;
            }

            $testType = $classTestType[$id] ?? ($hasCoverage ? 'coverage' : 'unknown');

            $classMetrics = [
                MetricKey::HAS_TEST => $hasTest,
                MetricKey::TEST_FILE_COUNT => $classTestCount[$id] ?? 0,
                MetricKey::TEST_TYPE => $testType,
            ];

            if (null !== $covEntry) {
                $classMetrics[MetricKey::LINE_COVERAGE] = round($covEntry['linerate'] * 100, 2);
                $totalWeightedLinerate += $covEntry['linerate'] * $covEntry['statements'];
                $totalStatements += $covEntry['statements'];
            }

            $this->metricsController->setMetricValuesByIdentifierString($id, $classMetrics);
        }

        $productionCount = count($productionIds);
        $functionBasedCount = count($this->testScanResult->functionBasedTestFiles);

        $testedClassRatio = $productionCount > 0
            ? round($testedCount / $productionCount * 100, 2)
            : 0.0;

        $projectMetrics = [
            MetricKey::OVERALL_TEST_FILE_COUNT => $totalTestFiles,
            MetricKey::OVERALL_PRODUCTION_FILE_COUNT => $productionCount,
            MetricKey::OVERALL_TEST_RATIO => $testedClassRatio,
            MetricKey::OVERALL_TESTED_CLASS_COUNT => $testedCount,
            MetricKey::OVERALL_UNTESTED_CLASS_COUNT => $untestedCount,
            MetricKey::OVERALL_TESTED_CLASS_RATIO => $testedClassRatio,
            MetricKey::OVERALL_FUNCTION_BASED_TEST_FILE_COUNT => $functionBasedCount,
            MetricKey::DETECTED_TEST_FRAMEWORKS => $this->frameworkDetection?->getTestFrameworkSummary() ?? '',
        ];

        if ($totalStatements > 0) {
            $projectMetrics[MetricKey::OVERALL_COVERAGE_PERCENT] = round($totalWeightedLinerate / $totalStatements * 100, 2);
        }

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $projectMetrics
        );
    }

    /**
     * Build indexes for production class lookup.
     *
     * @return array{array<string,string>, array<string,string>, string[], array<string,true>}
     *                                                                                         [shortNameIndex, fqcnIndex, productionIds, abstractIds]
     */
    private function buildProductionIndex(): array
    {
        $shortNameIndex = [];  // shortName → identifierString
        $fqcnIndex = [];       // fqcn → identifierString
        $productionIds = [];   // all non-interface/trait/enum identifier strings
        $abstractIds = [];     // identifierString → true, for abstract classes

        $testFilePaths = $this->testScanResult instanceof TestScanResult
            ? array_flip($this->testScanResult->classBasedTestFiles)
            : [];

        foreach ($this->metricsController->getAllCollections() as $identifierString => $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $filePath = $this->metricsController->getMetricValueByIdentifierString($identifierString, MetricKey::FILE_PATH)?->asString();
            if (is_string($filePath) && isset($testFilePaths[$filePath])) {
                continue;
            }

            $isInterface = $this->metricsController->getMetricValueByIdentifierString($identifierString, MetricKey::INTERFACE)?->asBool() ?? false;
            $isTrait = $this->metricsController->getMetricValueByIdentifierString($identifierString, MetricKey::TRAIT)?->asBool() ?? false;
            $isEnum = $this->metricsController->getMetricValueByIdentifierString($identifierString, MetricKey::ENUM)?->asBool() ?? false;

            if ($isInterface || $isTrait || $isEnum) {
                continue;
            }

            $isAbstract = $this->metricsController->getMetricValueByIdentifierString($identifierString, MetricKey::ABSTRACT)?->asBool() ?? false;

            $fqcn = $collection->getName();
            $shortName = basename(str_replace('\\', '/', $fqcn));

            $fqcnIndex[$fqcn] = $identifierString;
            $shortNameIndex[$shortName] = $identifierString;
            $productionIds[] = $identifierString;

            if ($isAbstract) {
                $abstractIds[$identifierString] = true;
            }
        }

        return [$shortNameIndex, $fqcnIndex, $productionIds, $abstractIds];
    }

    /**
     * Resolve 0-N production class identifiers for a test file.
     * Priority: @covers > use-statements (integration) > naming convention.
     *
     * @param array<string, string> $shortNameIndex
     * @param array<string, string> $fqcnIndex
     *
     * @return string[]
     */
    private function resolveProductionClasses(string $testFile, array $shortNameIndex, array $fqcnIndex): array
    {
        // Priority 1: @covers/@coversClass annotations
        if ($this->coversParseResult instanceof TestCoversParseResult) {
            $covers = $this->coversParseResult->coversMap[$testFile] ?? [];
            if (!empty($covers)) {
                return $this->resolveFqcnsToIds($covers, $shortNameIndex, $fqcnIndex);
            }
        }

        // Priority 2: use-statements for integration/feature tests
        $testType = $this->testScanResult->testFileToType[$testFile] ?? 'unknown';
        if ('integration' === $testType && $this->coversParseResult instanceof TestCoversParseResult) {
            $uses = $this->coversParseResult->useStatementsMap[$testFile] ?? [];
            if (!empty($uses)) {
                $resolved = $this->resolveFqcnsToIds($uses, $shortNameIndex, $fqcnIndex);
                if ([] !== $resolved) {
                    return $resolved;
                }
            }
        }

        // Priority 3: existing single-class fallback
        $single = $this->resolveProductionClass($testFile, $shortNameIndex, $fqcnIndex);

        return null !== $single ? [$single] : [];
    }

    /**
     * @param string[]              $names
     * @param array<string, string> $shortNameIndex
     * @param array<string, string> $fqcnIndex
     *
     * @return string[]
     */
    private function resolveFqcnsToIds(array $names, array $shortNameIndex, array $fqcnIndex): array
    {
        $result = [];
        foreach ($names as $name) {
            if (isset($fqcnIndex[$name])) {
                $result[] = $fqcnIndex[$name];
            } elseif (isset($shortNameIndex[$name])) {
                $result[] = $shortNameIndex[$name];
            }
        }

        return array_values(array_unique($result));
    }

    private function bestTestType(?string $existing, string $new): string
    {
        $priority = ['unit' => 3, 'integration' => 2, 'unknown' => 1];
        if (null === $existing) {
            return $new;
        }

        return ($priority[$new] ?? 0) > ($priority[$existing] ?? 0) ? $new : $existing;
    }

    /**
     * @param array<string, string> $shortNameIndex
     * @param array<string, string> $fqcnIndex
     */
    private function resolveProductionClass(string $testFile, array $shortNameIndex, array $fqcnIndex): ?string
    {
        // Strategy 1: PSR-4 namespace mapping
        if ($this->frameworkDetection instanceof FrameworkDetectionResult) {
            $result = $this->resolveViaPsr4($testFile, $fqcnIndex);
            if (null !== $result) {
                return $result;
            }
        }

        // Strategy 2: Naming convention — strip Test/Spec suffix, look up short name
        $className = basename($testFile, '.php');
        $productionName = $this->stripTestSuffix($className);
        if ($productionName !== $className && isset($shortNameIndex[$productionName])) {
            return $shortNameIndex[$productionName];
        }

        return null;
    }

    /**
     * @param array<string, string> $fqcnIndex
     */
    private function resolveViaPsr4(string $testFile, array $fqcnIndex): ?string
    {
        if (null === $this->frameworkDetection) {
            return null;
        }

        $psr4Dev = $this->frameworkDetection->psr4AutoloadDev;
        $psr4Prod = $this->frameworkDetection->psr4Autoload;

        if ([] === $psr4Dev || [] === $psr4Prod) {
            return null;
        }

        $normalizedFile = str_replace('\\', '/', $testFile);

        foreach ($psr4Dev as $devNamespace => $devPath) {
            $normalizedDevPath = str_replace('\\', '/', rtrim($devPath, '/\\'));

            // Locate the PSR-4 root within the file path
            $pos = strpos($normalizedFile, $normalizedDevPath.'/');
            if (false === $pos) {
                continue;
            }

            $relativePart = substr($normalizedFile, $pos + strlen($normalizedDevPath) + 1);
            // Strip .php extension and convert slashes to namespace separators
            $classPath = str_replace('/', '\\', substr($relativePart, 0, -4));
            $fullTestNamespace = rtrim($devNamespace, '\\').'\\'.$classPath;

            // Strip Test/Spec suffix from the class name part
            $parts = explode('\\', $fullTestNamespace);
            $lastPart = array_pop($parts);
            $productionClassName = $this->stripTestSuffix($lastPart);
            if ($productionClassName === $lastPart) {
                continue; // No test suffix to strip
            }
            $parts[] = $productionClassName;
            $withoutSuffix = implode('\\', $parts);

            // Strip the dev namespace prefix to get the relative class path
            $devNamespacePrefix = rtrim($devNamespace, '\\').'\\';
            if (!str_starts_with($withoutSuffix, $devNamespacePrefix)) {
                continue;
            }
            $remainingPath = substr($withoutSuffix, strlen($devNamespacePrefix));

            // Strip test-type subdirectory prefix (Unit\, Feature\, Integration\)
            foreach (['Unit\\', 'Feature\\', 'Integration\\'] as $typePrefix) {
                if (str_starts_with($remainingPath, $typePrefix)) {
                    $remainingPath = substr($remainingPath, strlen($typePrefix));
                    break;
                }
            }

            // Try all production namespace roots
            foreach (array_keys($psr4Prod) as $prodNamespace) {
                $candidate = rtrim($prodNamespace, '\\').'\\'.$remainingPath;
                if (isset($fqcnIndex[$candidate])) {
                    return $fqcnIndex[$candidate];
                }
            }
        }

        return null;
    }

    private function stripTestSuffix(string $className): string
    {
        foreach (['Tests', 'Test', 'Spec'] as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return substr($className, 0, -strlen($suffix));
            }
        }

        return $className;
    }

    private function setZeroProjectMetrics(): void
    {
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_TEST_FILE_COUNT => 0,
                MetricKey::OVERALL_PRODUCTION_FILE_COUNT => 0,
                MetricKey::OVERALL_TEST_RATIO => 0.0,
                MetricKey::OVERALL_TESTED_CLASS_COUNT => 0,
                MetricKey::OVERALL_UNTESTED_CLASS_COUNT => 0,
                MetricKey::OVERALL_TESTED_CLASS_RATIO => 0.0,
                MetricKey::OVERALL_FUNCTION_BASED_TEST_FILE_COUNT => 0,
                MetricKey::DETECTED_TEST_FRAMEWORKS => $this->frameworkDetection?->getTestFrameworkSummary() ?? '',
            ]
        );
    }

    /**
     * Look up coverage data for a class by its identifier string.
     * Resolves the absolute filePath metric to a relative path for matching.
     *
     * @return array{linerate: float, statements: int}|null
     */
    private function lookupCoverage(string $identifierString): ?array
    {
        $absolutePath = $this->metricsController
            ->getMetricValueByIdentifierString($identifierString, MetricKey::FILE_PATH)
            ?->asString();

        if (!is_string($absolutePath) || '' === $absolutePath) {
            return null;
        }

        $normalized = str_replace('\\', '/', $absolutePath);

        // Try direct match after stripping projectRoot prefix
        if (null !== $this->projectRoot) {
            $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
            if (str_starts_with($normalized, $root.'/')) {
                $relative = substr($normalized, strlen($root) + 1);
                if (isset($this->coverageData[$relative])) {
                    return $this->coverageData[$relative];
                }
            }
        }

        // Fallback: match by suffix in both directions
        // filePath may be shorter (relative) or longer (absolute) than coverage keys
        foreach ($this->coverageData ?? [] as $covPath => $covData) {
            $normalizedCovPath = str_replace('\\', '/', $covPath);
            if (str_ends_with($normalized, '/'.$normalizedCovPath)
                || str_ends_with($normalizedCovPath, '/'.$normalized)
                || $normalized === $normalizedCovPath) {
                return $covData;
            }
        }

        return null;
    }
}
