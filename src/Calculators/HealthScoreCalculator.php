<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class HealthScoreCalculator implements CalculatorInterface
{
    public function __construct(
        private readonly MetricsWriterInterface $writer,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ProjectMetricsCollection) {
            return;
        }

        $result = $this->computeScore($metrics);

        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::HEALTH_SCORE => round($result['healthScore'], 1),
                MetricKey::HEALTH_SCORE_GRADE => $this->scoreToGrade($result['healthScore']),
                MetricKey::HEALTH_SCORE_VERSION => 2,
                MetricKey::OVERALL_HTML_RATIO => $result['overallHtmlRatio'],
                MetricKey::OVERALL_PUBLIC_METHOD_RATIO => $result['overallPublicMethodRatio'],
                MetricKey::OVERALL_STATIC_METHOD_RATIO => $result['overallStaticMethodRatio'],
                MetricKey::OVERALL_ENCAPSULATION_SCORE => $result['overallEncapsulationScore'],
            ]
        );
    }

    /**
     * @return array{healthScore: float, overallHtmlRatio: float, overallPublicMethodRatio: float, overallStaticMethodRatio: float, overallEncapsulationScore: float}
     */
    private function computeScore(ProjectMetricsCollection $metrics): array
    {
        $scores = [];
        $weights = [];

        // 1. Maintainability Index (13%) — realistic scale: MI 40=0, MI 120+=100
        $avgMI = $metrics->getFloat(MetricKey::OVERALL_AVG_MI);
        $miScore = min(100, max(0, ($avgMI - 40) * 1.25));
        $scores[] = $miScore;
        $weights[] = 0.13;

        // 2. Problem density (9%) — logarithmic decay for graceful degradation
        $errors = $metrics->getInt(MetricKey::OVERALL_ERROR_COUNT);
        $warnings = $metrics->getInt(MetricKey::OVERALL_WARNING_COUNT);
        $classes = $metrics->getInt(MetricKey::OVERALL_CLASSES);
        $files = $metrics->getInt(MetricKey::OVERALL_FILES);
        $totalEntities = max($classes + $files, 1);
        $problemDensity = ($errors + $warnings) / $totalEntities;
        $problemScore = max(0, 100 - 30 * log(1 + $problemDensity));
        $scores[] = $problemScore;
        $weights[] = 0.09;

        // 3. Cyclomatic Complexity (9%) — lower is better
        $avgCC = $metrics->getFloat(MetricKey::OVERALL_AVG_CC);
        $ccScore = max(0, min(100, 100 - ($avgCC - 1) * 5));
        $scores[] = $ccScore;
        $weights[] = 0.09;

        // 4. Coupling — Distance from main sequence (9%) — lower is better
        $avgDistance = abs($metrics->getFloat(MetricKey::OVERALL_DISTANCE_FROM_MAINLINE));
        $couplingScore = max(0, min(100, (1 - $avgDistance) * 100));
        $scores[] = $couplingScore;
        $weights[] = 0.09;

        // 5. Code structure balance (5%) — LLOC outside classes/functions should be low
        $lloc = $metrics->getInt(MetricKey::OVERALL_LLOC) ?: 1;
        $llocOutside = $metrics->getInt(MetricKey::OVERALL_LLOC_OUTSIDE);
        $outsideRatio = $lloc > 0 ? $llocOutside / $lloc : 0;
        $structureScore = max(0, (1 - $outsideRatio) * 100);
        $scores[] = $structureScore;
        $weights[] = 0.05;

        // 6. HTML-in-PHP ratio (13%) — cubic decay: punishes heavy HTML mixing
        $htmlLoc = $metrics->getInt(MetricKey::OVERALL_HTML_LOC);
        $totalLoc = $metrics->getInt(MetricKey::OVERALL_LOC) ?: 1;
        $htmlRatio = $totalLoc > 0 ? $htmlLoc / $totalLoc : 0;
        $htmlScore = 100 * (1 - $htmlRatio) ** 3;
        $scores[] = $htmlScore;
        $weights[] = 0.13;

        // 7. Encapsulation quality (13%) — visibility distribution + static method ratio
        $totalMethods = $metrics->getInt(MetricKey::OVERALL_METHODS_COUNT);
        $publicMethods = $metrics->getInt(MetricKey::OVERALL_PUBLIC_METHODS_COUNT);
        $staticMethods = $metrics->getInt(MetricKey::OVERALL_STATIC_METHODS_COUNT);

        if ($totalMethods > 0) {
            $privateRatio = ($totalMethods - $publicMethods) / $totalMethods;
            $publicMethodRatio = $publicMethods / $totalMethods;
            $staticMethodRatio = $staticMethods / $totalMethods;

            // Non-public score: reaches 100 at 30% non-public methods
            $privateScore = min(100, $privateRatio * 333);

            // Static penalty: free zone up to 10%, then -20pts per additional 10%
            $staticScore = max(0, min(100, 100 - max(0, $staticMethodRatio - 0.10) * 200));

            $encapsulationScore = 0.6 * $privateScore + 0.4 * $staticScore;
        } else {
            $publicMethodRatio = 0;
            $staticMethodRatio = 0;
            $encapsulationScore = 100;
        }

        $scores[] = $encapsulationScore;
        $weights[] = 0.13;

        // 8. Dependency health (9%) — penalizes cycle breadth and count
        $classesInCycles = $metrics->getInt(MetricKey::OVERALL_CLASSES_IN_CYCLES);
        $depCycles = $metrics->getInt(MetricKey::OVERALL_DEPENDENCY_CYCLES);

        if ($classes > 0) {
            $cycleRatio = $classesInCycles / $classes;
            $depScore = max(0, min(100, 100 * (1 - $cycleRatio) ** 2 - $depCycles * 5));
        } else {
            $depScore = 100;
        }

        $scores[] = $depScore;
        $weights[] = 0.09;

        // 9. Abstractness (10%) — projects need interfaces/abstract classes
        $abstractness = abs($metrics->getFloat(MetricKey::OVERALL_ABSTRACTNESS));
        // Reaches 100 at 10% abstractness (interfaces + abstract classes / total)
        $abstractScore = min(100, $abstractness * 1000);
        $scores[] = $abstractScore;
        $weights[] = 0.10;

        // 10. Test coverage (10%) — prefer Clover XML line coverage, fall back to class ratio
        $coveragePercentValue = $metrics->get(MetricKey::OVERALL_COVERAGE_PERCENT);
        $testedClassRatioValue = $metrics->get(MetricKey::OVERALL_TESTED_CLASS_RATIO);
        $testFileCount = $metrics->getInt(MetricKey::OVERALL_TEST_FILE_COUNT);

        if (null !== $coveragePercentValue) {
            $testScore = min(100.0, $coveragePercentValue->asFloat());
            $scores[] = $testScore;
            $weights[] = 0.10;
        } elseif (null !== $testedClassRatioValue && $testFileCount > 0) {
            $testScore = min(100.0, $testedClassRatioValue->asFloat());
            $scores[] = $testScore;
            $weights[] = 0.10;
        }
        // If no test data: don't add the factor at all — remaining weights auto-normalize

        // Weighted average
        $healthScore = $this->weightedAverage($scores, $weights);

        return [
            'healthScore' => $healthScore,
            'overallHtmlRatio' => round($htmlRatio * 100, 1),
            'overallPublicMethodRatio' => round($publicMethodRatio * 100, 1),
            'overallStaticMethodRatio' => round($staticMethodRatio * 100, 1),
            'overallEncapsulationScore' => round($encapsulationScore, 1),
        ];
    }

    /**
     * @param float[] $scores
     * @param float[] $weights
     */
    private function weightedAverage(array $scores, array $weights): float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;
        foreach ($scores as $i => $score) {
            $weight = $weights[$i] ?? 0.0;
            $totalWeight += $weight;
            $weightedSum += $score * $weight;
        }

        return $totalWeight > 0.0 ? $weightedSum / $totalWeight : 0.0;
    }

    private function scoreToGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }
}
