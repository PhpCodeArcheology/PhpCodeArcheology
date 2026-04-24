<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;

final class CalculatorRegistry
{
    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsWriterInterface $writer,
        private readonly MetricsRegistryInterface $registry,
    ) {
    }

    /** @return list<CalculatorInterface> */
    public function getMainCalculators(Config $config): array
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($this->reader, $this->writer);

        $frameworkDetectionRaw = $config->get('frameworkDetection');
        $frameworkDetection = $frameworkDetectionRaw instanceof FrameworkDetectionResult ? $frameworkDetectionRaw : null;
        $testScanResultRaw = $config->get('testScanResult');
        $testScanResult = $testScanResultRaw instanceof TestScanResult ? $testScanResultRaw : null;
        $coverageDataRaw = $config->get('coverageData');
        $coverageData = $this->extractCoverageData($coverageDataRaw);
        $coversParseResultRaw = $config->get('coversParseResult');
        $coversParseResult = $coversParseResultRaw instanceof TestCoversParseResult ? $coversParseResultRaw : null;

        $runningDirRaw = $config->get('runningDir');
        $filesRaw = $config->get('files');
        $composerRoot = ($frameworkDetection instanceof FrameworkDetectionResult && '' !== $frameworkDetection->composerJsonPath)
            ? dirname($frameworkDetection->composerJsonPath)
            : (is_string($runningDirRaw) && '' !== $runningDirRaw
                ? $runningDirRaw
                : (is_array($filesRaw) && is_string($filesRaw[0] ?? null) ? $filesRaw[0] : (getcwd() ?: '')));

        return [
            new MaintainabilityIndexCalculator($this->writer),
            new FileCalculator($this->reader, $this->writer, $this->registry),
            new TestMappingCalculator($this->reader, $this->writer, $this->registry, $frameworkDetection, $testScanResult, $coverageData, $composerRoot, $coversParseResult),
            new VariablesCalculator($this->reader, $this->writer, $this->registry),
            new CouplingCalculator($this->reader, $this->writer, $packageIACalculator),
            new InheritanceDepthCalculator($this->reader, $this->writer, $this->registry),
            new DependencyCycleCalculator($this->reader, $this->writer, $this->registry),
            new SolidViolationCalculator($this->reader, $this->writer, $this->registry),
            new LayerViolationCalculator($this->reader, $this->writer, $this->registry),
            new PackageCohesionCalculator($this->reader, $this->writer, $this->registry),
            new CodeDuplicationCalculator($this->reader, $this->writer, $this->registry),
            new ProjectCalculator($this->reader, $this->writer, $this->registry),
            new LimitsAndAveragesCalculator($this->reader, $this->writer, $this->registry),
        ];
    }

    /** @return list<CalculatorInterface> */
    public function getQuickCalculators(): array
    {
        return [
            new MaintainabilityIndexCalculator($this->writer),
            new FileCalculator($this->reader, $this->writer, $this->registry),
            new ProjectCalculator($this->reader, $this->writer, $this->registry),
            new LimitsAndAveragesCalculator($this->reader, $this->writer, $this->registry),
        ];
    }

    /** @return list<CalculatorInterface> */
    public function getPostPredictionCalculators(): array
    {
        return [
            new TechnicalDebtCalculator($this->writer),
            new HealthScoreCalculator($this->writer),
            new RefactoringPriorityCalculator($this->writer, $this->registry),
        ];
    }

    /**
     * @return array<string, array{linerate: float, statements: int}>|null
     */
    private function extractCoverageData(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $result = [];
        foreach ($raw as $key => $entry) {
            if (!is_string($key) || !is_array($entry)) {
                continue;
            }
            $linerate = isset($entry['linerate']) && is_float($entry['linerate']) ? $entry['linerate'] : (isset($entry['linerate']) && is_numeric($entry['linerate']) ? (float) $entry['linerate'] : 0.0);
            $statements = isset($entry['statements']) && is_int($entry['statements']) ? $entry['statements'] : (isset($entry['statements']) && is_numeric($entry['statements']) ? (int) $entry['statements'] : 0);
            $result[$key] = ['linerate' => $linerate, 'statements' => $statements];
        }

        return [] === $result ? null : $result;
    }
}
