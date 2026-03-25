<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class TestMappingCalculator implements CalculatorInterface
{
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
        $totalTestFiles = count($this->testScanResult?->classBasedTestFiles ?? [])
            + count($this->testScanResult?->functionBasedTestFiles ?? []);

        if ($this->testScanResult === null || $totalTestFiles === 0) {
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
            $hasTest = isset($classTestCount[$id]);
            if ($hasTest) {
                $testedCount++;
            } elseif (!isset($abstractIds[$id])) {
                // Abstract classes are excluded from the "untested" count
                $untestedCount++;
            }

            $metrics = [
                'hasTest' => $hasTest,
                'testFileCount' => $classTestCount[$id] ?? 0,
                'testType' => $classTestType[$id] ?? 'unknown',
            ];

            if (!empty($this->coverageData)) {
                $covEntry = $this->lookupCoverage($id);
                if ($covEntry !== null) {
                    $metrics['lineCoverage'] = round($covEntry['linerate'] * 100, 2);
                    $totalWeightedLinerate += $covEntry['linerate'] * $covEntry['statements'];
                    $totalStatements += $covEntry['statements'];
                }
            }

            $this->metricsController->setMetricValuesByIdentifierString($id, $metrics);
        }

        $productionCount = count($productionIds);
        $functionBasedCount = count($this->testScanResult->functionBasedTestFiles);

        $testRatio = $productionCount > 0
            ? round($totalTestFiles / $productionCount * 100, 2)
            : 0.0;
        $testedClassRatio = $productionCount > 0
            ? round($testedCount / $productionCount * 100, 2)
            : 0.0;

        $projectMetrics = [
            'overallTestFileCount' => $totalTestFiles,
            'overallProductionFileCount' => $productionCount,
            'overallTestRatio' => $testRatio,
            'overallTestedClassCount' => $testedCount,
            'overallUntestedClassCount' => $untestedCount,
            'overallTestedClassRatio' => $testedClassRatio,
            'overallFunctionBasedTestFileCount' => $functionBasedCount,
            'detectedTestFrameworks' => $this->frameworkDetection?->getTestFrameworkSummary() ?? '',
        ];

        if ($totalStatements > 0) {
            $projectMetrics['overallCoveragePercent'] = round($totalWeightedLinerate / $totalStatements * 100, 2);
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
     *         [shortNameIndex, fqcnIndex, productionIds, abstractIds]
     */
    private function buildProductionIndex(): array
    {
        $shortNameIndex = [];  // shortName → identifierString
        $fqcnIndex = [];       // fqcn → identifierString
        $productionIds = [];   // all non-interface/trait/enum identifier strings
        $abstractIds = [];     // identifierString → true, for abstract classes

        $testFilePaths = $this->testScanResult !== null
            ? array_flip($this->testScanResult->classBasedTestFiles)
            : [];

        foreach ($this->metricsController->getAllCollections() as $identifierString => $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $filePath = $this->metricsController->getMetricValueByIdentifierString($identifierString, 'filePath')?->getValue();
            if (is_string($filePath) && isset($testFilePaths[$filePath])) {
                continue;
            }

            $isInterface = (bool) ($this->metricsController->getMetricValueByIdentifierString($identifierString, 'interface')?->getValue() ?? false);
            $isTrait = (bool) ($this->metricsController->getMetricValueByIdentifierString($identifierString, 'trait')?->getValue() ?? false);
            $isEnum = (bool) ($this->metricsController->getMetricValueByIdentifierString($identifierString, 'enum')?->getValue() ?? false);

            if ($isInterface || $isTrait || $isEnum) {
                continue;
            }

            $isAbstract = (bool) ($this->metricsController->getMetricValueByIdentifierString($identifierString, 'abstract')?->getValue() ?? false);

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
     */
    private function resolveProductionClasses(string $testFile, array $shortNameIndex, array $fqcnIndex): array
    {
        // Priority 1: @covers/@coversClass annotations
        if ($this->coversParseResult !== null) {
            $covers = $this->coversParseResult->coversMap[$testFile] ?? [];
            if (!empty($covers)) {
                return $this->resolveFqcnsToIds($covers, $shortNameIndex, $fqcnIndex);
            }
        }

        // Priority 2: use-statements for integration/feature tests
        $testType = $this->testScanResult->testFileToType[$testFile] ?? 'unknown';
        if ($testType === 'integration' && $this->coversParseResult !== null) {
            $uses = $this->coversParseResult->useStatementsMap[$testFile] ?? [];
            if (!empty($uses)) {
                $resolved = $this->resolveFqcnsToIds($uses, $shortNameIndex, $fqcnIndex);
                if (!empty($resolved)) {
                    return $resolved;
                }
            }
        }

        // Priority 3: existing single-class fallback
        $single = $this->resolveProductionClass($testFile, $shortNameIndex, $fqcnIndex);
        return $single !== null ? [$single] : [];
    }

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
        if ($existing === null) {
            return $new;
        }
        return ($priority[$new] ?? 0) > ($priority[$existing] ?? 0) ? $new : $existing;
    }

    private function resolveProductionClass(string $testFile, array $shortNameIndex, array $fqcnIndex): ?string
    {
        // Strategy 1: PSR-4 namespace mapping
        if ($this->frameworkDetection !== null) {
            $result = $this->resolveViaPsr4($testFile, $fqcnIndex);
            if ($result !== null) {
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

    private function resolveViaPsr4(string $testFile, array $fqcnIndex): ?string
    {
        $psr4Dev = $this->frameworkDetection?->psr4AutoloadDev ?? [];
        $psr4Prod = $this->frameworkDetection?->psr4Autoload ?? [];

        if (empty($psr4Dev) || empty($psr4Prod)) {
            return null;
        }

        $normalizedFile = str_replace('\\', '/', $testFile);

        foreach ($psr4Dev as $devNamespace => $devPath) {
            $normalizedDevPath = str_replace('\\', '/', rtrim($devPath, '/\\'));

            // Locate the PSR-4 root within the file path
            $pos = strpos($normalizedFile, $normalizedDevPath . '/');
            if ($pos === false) {
                continue;
            }

            $relativePart = substr($normalizedFile, $pos + strlen($normalizedDevPath) + 1);
            // Strip .php extension and convert slashes to namespace separators
            $classPath = str_replace('/', '\\', substr($relativePart, 0, -4));
            $fullTestNamespace = rtrim($devNamespace, '\\') . '\\' . $classPath;

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
            $devNamespacePrefix = rtrim($devNamespace, '\\') . '\\';
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
            foreach ($psr4Prod as $prodNamespace => $prodPath) {
                $candidate = rtrim($prodNamespace, '\\') . '\\' . $remainingPath;
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
                'overallTestFileCount' => 0,
                'overallProductionFileCount' => 0,
                'overallTestRatio' => 0.0,
                'overallTestedClassCount' => 0,
                'overallUntestedClassCount' => 0,
                'overallTestedClassRatio' => 0.0,
                'overallFunctionBasedTestFileCount' => 0,
                'detectedTestFrameworks' => $this->frameworkDetection?->getTestFrameworkSummary() ?? '',
            ]
        );
    }

    /**
     * Look up coverage data for a class by its identifier string.
     * Resolves the absolute filePath metric to a relative path for matching.
     */
    private function lookupCoverage(string $identifierString): ?array
    {
        $absolutePath = $this->metricsController
            ->getMetricValueByIdentifierString($identifierString, 'filePath')
            ?->getValue();

        if (!is_string($absolutePath) || $absolutePath === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $absolutePath);

        // Try direct match after stripping projectRoot prefix
        if ($this->projectRoot !== null) {
            $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
            if (str_starts_with($normalized, $root . '/')) {
                $relative = substr($normalized, strlen($root) + 1);
                if (isset($this->coverageData[$relative])) {
                    return $this->coverageData[$relative];
                }
            }
        }

        // Fallback: match by suffix in both directions
        // filePath may be shorter (relative) or longer (absolute) than coverage keys
        foreach ($this->coverageData as $covPath => $covData) {
            $normalizedCovPath = str_replace('\\', '/', $covPath);
            if (str_ends_with($normalized, '/' . $normalizedCovPath)
                || str_ends_with($normalizedCovPath, '/' . $normalized)
                || $normalized === $normalizedCovPath) {
                return $covData;
            }
        }

        return null;
    }
}
