<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class HealthScoreCalculator implements CalculatorInterface
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ProjectMetricsCollection) {
            return;
        }

        $result = $this->computeScore($metrics);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'healthScore' => round($result['healthScore'], 1),
                'healthScoreGrade' => $this->scoreToGrade($result['healthScore']),
                'healthScoreVersion' => 2,
                'overallHtmlRatio' => $result['overallHtmlRatio'],
                'overallPublicMethodRatio' => $result['overallPublicMethodRatio'],
                'overallStaticMethodRatio' => $result['overallStaticMethodRatio'],
                'overallEncapsulationScore' => $result['overallEncapsulationScore'],
            ]
        );
    }

    private function computeScore(ProjectMetricsCollection $metrics): array
    {
        $scores = [];
        $weights = [];

        // 1. Maintainability Index (15%) — realistic scale: MI 40=0, MI 120+=100
        $avgMI = $metrics->get('overallAvgMI')?->getValue() ?? 0;
        $miScore = min(100, max(0, ($avgMI - 40) * 1.25));
        $scores[] = $miScore;
        $weights[] = 0.13;

        // 2. Problem density (10%) — logarithmic decay for graceful degradation
        $errors = $metrics->get('overallErrorCount')?->getValue() ?? 0;
        $warnings = $metrics->get('overallWarningCount')?->getValue() ?? 0;
        $classes = $metrics->get('overallClasses')?->getValue() ?? 1;
        $files = $metrics->get('overallFiles')?->getValue() ?? 1;
        $totalEntities = max($classes + $files, 1);
        $problemDensity = ($errors + $warnings) / $totalEntities;
        $problemScore = max(0, 100 - 30 * log(1 + $problemDensity));
        $scores[] = $problemScore;
        $weights[] = 0.09;

        // 3. Cyclomatic Complexity (10%) — lower is better
        $avgCC = $metrics->get('overallAvgCC')?->getValue() ?? 0;
        $ccScore = max(0, min(100, 100 - ($avgCC - 1) * 5));
        $scores[] = $ccScore;
        $weights[] = 0.09;

        // 4. Coupling — Distance from main sequence (10%) — lower is better
        $avgDistance = abs($metrics->get('overallDistanceFromMainline')?->getValue() ?? 0);
        $couplingScore = max(0, min(100, (1 - $avgDistance) * 100));
        $scores[] = $couplingScore;
        $weights[] = 0.09;

        // 5. Code structure balance (5%) — LLOC outside classes/functions should be low
        $lloc = $metrics->get('overallLloc')?->getValue() ?? 1;
        $llocOutside = $metrics->get('overallLlocOutside')?->getValue() ?? 0;
        $outsideRatio = $lloc > 0 ? $llocOutside / $lloc : 0;
        $structureScore = max(0, (1 - $outsideRatio) * 100);
        $scores[] = $structureScore;
        $weights[] = 0.05;

        // 6. HTML-in-PHP ratio (15%) — cubic decay: punishes heavy HTML mixing
        $htmlLoc = $metrics->get('overallHtmlLoc')?->getValue() ?? 0;
        $totalLoc = $metrics->get('overallLoc')?->getValue() ?? 1;
        $htmlRatio = $totalLoc > 0 ? $htmlLoc / $totalLoc : 0;
        $htmlScore = 100 * pow(1 - $htmlRatio, 3);
        $scores[] = $htmlScore;
        $weights[] = 0.13;

        // 7. Encapsulation quality (15%) — visibility distribution + static method ratio
        $totalMethods = $metrics->get('overallMethodsCount')?->getValue() ?? 0;
        $publicMethods = $metrics->get('overallPublicMethodsCount')?->getValue() ?? 0;
        $staticMethods = $metrics->get('overallStaticMethodsCount')?->getValue() ?? 0;

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

        // 8. Dependency health (10%) — penalizes cycle breadth and count
        $classesInCycles = $metrics->get('overallClassesInCycles')?->getValue() ?? 0;
        $depCycles = $metrics->get('overallDependencyCycles')?->getValue() ?? 0;

        if ($classes > 0) {
            $cycleRatio = $classesInCycles / $classes;
            $depScore = max(0, min(100, 100 * pow(1 - $cycleRatio, 2) - $depCycles * 5));
        } else {
            $depScore = 100;
        }

        $scores[] = $depScore;
        $weights[] = 0.09;

        // 9. Abstractness (10%) — projects need interfaces/abstract classes
        $abstractness = abs($metrics->get('overallAbstractness')?->getValue() ?? 0);
        // Reaches 100 at 10% abstractness (interfaces + abstract classes / total)
        $abstractScore = min(100, $abstractness * 1000);
        $scores[] = $abstractScore;
        $weights[] = 0.10;

        // 10. Test coverage (10%) — prefer Clover XML line coverage, fall back to class ratio
        $coveragePercent = $metrics->get('overallCoveragePercent')?->getValue() ?? null;
        $testedClassRatio = $metrics->get('overallTestedClassRatio')?->getValue() ?? null;
        $testFileCount = $metrics->get('overallTestFileCount')?->getValue() ?? 0;

        if ($coveragePercent !== null) {
            $testScore = min(100, $coveragePercent);
            $scores[] = $testScore;
            $weights[] = 0.10;
        } elseif ($testedClassRatio !== null && $testFileCount > 0) {
            $testScore = min(100, $testedClassRatio);
            $scores[] = $testScore;
            $weights[] = 0.10;
        }
        // If no test data: don't add the factor at all — remaining weights auto-normalize

        // Weighted average
        $totalWeight = array_sum($weights);
        $weightedSum = 0;
        for ($i = 0; $i < count($scores); $i++) {
            $weightedSum += $scores[$i] * $weights[$i];
        }

        $healthScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;

        return [
            'healthScore' => $healthScore,
            'overallHtmlRatio' => round($htmlRatio * 100, 1),
            'overallPublicMethodRatio' => round($publicMethodRatio * 100, 1),
            'overallStaticMethodRatio' => round($staticMethodRatio * 100, 1),
            'overallEncapsulationScore' => round($encapsulationScore, 1),
        ];
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
