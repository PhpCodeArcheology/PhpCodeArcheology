<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;

final class CalculatorRegistry
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    /** @return list<CalculatorInterface> */
    public function getMainCalculators(Config $config): array
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($this->metricsController);

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
            new MaintainabilityIndexCalculator($this->metricsController),
            new FileCalculator($this->metricsController),
            new TestMappingCalculator($this->metricsController, $frameworkDetection, $testScanResult, $coverageData, $composerRoot, $coversParseResult),
            new VariablesCalculator($this->metricsController),
            new CouplingCalculator($this->metricsController, $packageIACalculator),
            new InheritanceDepthCalculator($this->metricsController),
            new DependencyCycleCalculator($this->metricsController),
            new SolidViolationCalculator($this->metricsController),
            new LayerViolationCalculator($this->metricsController),
            new PackageCohesionCalculator($this->metricsController),
            new CodeDuplicationCalculator($this->metricsController),
            new ProjectCalculator($this->metricsController),
            new LimitsAndAveragesCalculator($this->metricsController),
        ];
    }

    /** @return list<CalculatorInterface> */
    public function getQuickCalculators(): array
    {
        return [
            new MaintainabilityIndexCalculator($this->metricsController),
            new FileCalculator($this->metricsController),
            new ProjectCalculator($this->metricsController),
            new LimitsAndAveragesCalculator($this->metricsController),
        ];
    }

    /** @return list<CalculatorInterface> */
    public function getPostPredictionCalculators(): array
    {
        return [
            new TechnicalDebtCalculator($this->metricsController),
            new HealthScoreCalculator($this->metricsController),
            new RefactoringPriorityCalculator($this->metricsController),
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
