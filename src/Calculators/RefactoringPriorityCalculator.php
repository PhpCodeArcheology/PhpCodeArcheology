<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Predictions\PredictionInterface;

class RefactoringPriorityCalculator implements CalculatorInterface
{
    private array $scores = [];

    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection) {
            return;
        }

        // Skip non-refactorable types
        if ($this->isSkippable($metrics)) {
            return;
        }

        // Count problems by level
        [$errorCount, $warningCount, $infoCount] = $this->countProblems($metrics);

        // Read metrics
        $cc = $metrics->get('cc')?->getValue() ?? 0;
        $lcom = $metrics->get('lcom')?->getValue() ?? 0;
        $lloc = $metrics->get('lloc')?->getValue() ?? 0;
        $inCycle = (bool) ($metrics->get('inDependencyCycle')?->getValue() ?? false);
        $cycleLength = $metrics->get('dependencyCycleLength')?->getValue() ?? 0;
        $layerViolations = $metrics->get('layerViolationCount')?->getValue() ?? 0;
        $solidViolations = $metrics->get('solidViolationCount')?->getValue() ?? 0;
        $usedFromOutside = $metrics->get('usedFromOutsideCount')?->getValue() ?? 0;
        $hasTest = (bool) ($metrics->get('hasTest')?->getValue() ?? false);

        // Git metrics (file-level, looked up via class filePath)
        $gitChurn = $this->getFileGitMetric($metrics, 'gitChurnCount');
        $gitAuthors = $this->getFileGitMetric($metrics, 'gitAuthorCount');
        $gitAgeDays = $this->getFileGitMetric($metrics, 'gitCodeAgeDays');

        // Gate: zero problems AND no structural issues → score 0
        $totalProblems = $errorCount + $warningCount + $infoCount;
        $hasStructuralIssues = $inCycle || $layerViolations > 0 || $solidViolations > 0 || $lcom > 1;

        if ($totalProblems === 0 && !$hasStructuralIssues) {
            $this->storeResult($metrics, 0, '', []);
            return;
        }

        // Sub-scores for severity
        $problemScore = min(100, $errorCount * 12 + $warningCount * 4 + $infoCount);
        $complexityScore = min(100, max(0, ($cc - 5) * 4));
        $cohesionScore = min(100, max(0, ($lcom - 1) * 20));
        $sizeScore = min(100, max(0, ($lloc - 100) * 0.25));

        $structuralScore = min(100,
            ($inCycle ? 30 : 0)
            + min(30, $cycleLength * 5)
            + min(20, $layerViolations * 10)
            + min(20, $solidViolations * 5)
        );

        $severity = $problemScore * 0.30
            + $complexityScore * 0.25
            + $cohesionScore * 0.15
            + $sizeScore * 0.10
            + $structuralScore * 0.20;

        // Impact multiplier
        $impact = 1.0
            + min(0.4, $usedFromOutside * 0.04)
            + min(0.3, $gitChurn * 0.015)
            + min(0.15, $gitAuthors * 0.05)
            + ($gitAgeDays > 0 && $gitAgeDays < 90 ? 0.15 : ($gitAgeDays > 0 && $gitAgeDays < 365 ? 0.08 : 0));

        $priority = min(100, round($severity * $impact / 2.0, 1));

        // Build contextual recommendation
        $subScores = [
            'problems' => $problemScore,
            'complexity' => $complexityScore,
            'cohesion' => $cohesionScore,
            'size' => $sizeScore,
            'structural' => $structuralScore,
        ];

        [$recommendation, $drivers] = $this->buildRecommendation(
            $subScores,
            compact('cc', 'lcom', 'lloc', 'inCycle', 'cycleLength', 'layerViolations', 'solidViolations', 'hasTest'),
            $errorCount,
            $warningCount
        );

        $this->storeResult($metrics, $priority, $recommendation, $drivers);
    }

    public function afterTraverse(): void
    {
        $count = count($this->scores);
        $needingRefactoring = count(array_filter($this->scores, fn(float $s) => $s > 0));
        $avg = $count > 0 ? round(array_sum($this->scores) / $count, 1) : 0;
        $max = $count > 0 ? max($this->scores) : 0;

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallAvgRefactoringPriority' => $avg,
                'overallMaxRefactoringPriority' => $max,
                'overallClassesNeedingRefactoring' => $needingRefactoring,
            ]
        );
    }

    private function isSkippable(ClassMetricsCollection $metrics): bool
    {
        if (
            (bool) ($metrics->get('interface')?->getValue() ?? false)
            || (bool) ($metrics->get('trait')?->getValue() ?? false)
            || (bool) ($metrics->get('enum')?->getValue() ?? false)
        ) {
            return true;
        }

        $shortName = basename(str_replace('\\', '/', $metrics->getName()));
        return str_ends_with($shortName, 'Test')
            || str_ends_with($shortName, 'Spec')
            || str_ends_with($shortName, 'Cest');
    }

    private function countProblems(ClassMetricsCollection $metrics): array
    {
        $errors = 0;
        $warnings = 0;
        $infos = 0;

        foreach ($metrics->getAll() as $metricValue) {
            foreach ($metricValue->getProblems() as $problem) {
                match ($problem->getProblemLevel()) {
                    PredictionInterface::ERROR => $errors++,
                    PredictionInterface::WARNING => $warnings++,
                    PredictionInterface::INFO => $infos++,
                    default => null,
                };
            }
        }

        return [$errors, $warnings, $infos];
    }

    private ?array $fileCollectionIndex = null;

    private function getFileGitMetric(ClassMetricsCollection $metrics, string $key): int
    {
        $filePath = $metrics->get('filePath')?->getValue() ?? '';
        if ($filePath === '') {
            return 0;
        }

        if ($this->fileCollectionIndex === null) {
            $this->fileCollectionIndex = [];
            foreach ($this->metricsController->getAllCollections() as $collection) {
                if ($collection instanceof \PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection) {
                    $this->fileCollectionIndex[$collection->getPath()] = $collection;
                }
            }
        }

        $fileCollection = $this->fileCollectionIndex[$filePath] ?? null;
        if ($fileCollection === null) {
            return 0;
        }

        return (int) ($fileCollection->get($key)?->getValue() ?? 0);
    }

    private function buildRecommendation(
        array $subScores,
        array $raw,
        int $errorCount,
        int $warningCount,
    ): array {
        $drivers = [];
        $recommendations = [];

        if ($subScores['complexity'] >= 60) {
            $drivers[] = 'high complexity';
            $recommendations[] = sprintf(
                'CC=%d is very high. Extract methods or split into strategy/command objects.',
                $raw['cc']
            );
        }

        if (!($raw['hasTest'] ?? false) && $raw['cc'] > 5) {
            $drivers[] = 'untested';
            $recommendations[] = 'No tests found for this class. Add tests before refactoring to avoid regressions.';
        }

        if ($subScores['cohesion'] >= 40) {
            $drivers[] = 'low cohesion';
            $recommendations[] = sprintf(
                'LCOM=%d suggests %d+ responsibilities. Split into focused classes.',
                $raw['lcom'],
                $raw['lcom']
            );
        }

        if ($subScores['structural'] >= 40) {
            if ($raw['inCycle']) {
                $drivers[] = 'dependency cycle';
                $recommendations[] = sprintf(
                    'Part of a %d-class dependency cycle. Introduce interfaces to break the cycle.',
                    $raw['cycleLength']
                );
            }
            if ($raw['layerViolations'] > 0) {
                $drivers[] = 'layer violations';
                $recommendations[] = sprintf(
                    '%d layer violation(s). Move dependencies behind interfaces or restructure layers.',
                    $raw['layerViolations']
                );
            }
            if ($raw['solidViolations'] > 0) {
                $drivers[] = 'SOLID violations';
            }
        }

        if ($subScores['problems'] >= 50) {
            $drivers[] = 'many issues';
            $recommendations[] = sprintf(
                '%d errors and %d warnings. Address the errors first.',
                $errorCount,
                $warningCount
            );
        }

        if ($subScores['size'] >= 50) {
            $drivers[] = 'excessive size';
            $recommendations[] = sprintf(
                '%d logical lines. Extract helper classes or use composition.',
                $raw['lloc']
            );
        }

        if (empty($drivers) && $subScores['complexity'] > 0) {
            $drivers[] = 'moderate complexity';
            $recommendations[] = 'Minor issues detected. Consider simplifying when next modifying this class.';
        }

        return [
            implode(' ', array_slice($recommendations, 0, 3)),
            $drivers,
        ];
    }

    private function storeResult(ClassMetricsCollection $metrics, float $priority, string $recommendation, array $drivers): void
    {
        $this->scores[] = $priority;

        $this->metricsController->setMetricValueByIdentifierString(
            (string) $metrics->getIdentifier(),
            'refactoringPriority',
            $priority
        );

        $this->metricsController->setMetricValueByIdentifierString(
            (string) $metrics->getIdentifier(),
            'refactoringPriorityRecommendation',
            $recommendation
        );

        $this->metricsController->setMetricValueByIdentifierString(
            (string) $metrics->getIdentifier(),
            'refactoringPriorityDrivers',
            $drivers
        );
    }
}
