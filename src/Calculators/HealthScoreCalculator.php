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

        $score = $this->computeScore($metrics);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'healthScore' => round($score, 1),
                'healthScoreGrade' => $this->scoreToGrade($score),
            ]
        );
    }

    private function computeScore(ProjectMetricsCollection $metrics): float
    {
        $scores = [];
        $weights = [];

        // 1. Maintainability Index (30%) — realistic scale: MI 40=0, MI 120+=100
        $avgMI = $metrics->get('overallAvgMI')?->getValue() ?? 0;
        $miScore = min(100, max(0, ($avgMI - 40) * 1.25));
        $scores[] = $miScore;
        $weights[] = 0.30;

        // 2. Problem density (25%) — logarithmic decay for graceful degradation
        $errors = $metrics->get('overallErrorCount')?->getValue() ?? 0;
        $warnings = $metrics->get('overallWarningCount')?->getValue() ?? 0;
        $classes = $metrics->get('overallClasses')?->getValue() ?? 1;
        $files = $metrics->get('overallFiles')?->getValue() ?? 1;
        $totalEntities = max($classes + $files, 1);
        $problemDensity = ($errors + $warnings) / $totalEntities;
        $problemScore = max(0, 100 - 30 * log(1 + $problemDensity));
        $scores[] = $problemScore;
        $weights[] = 0.25;

        // 3. Cyclomatic Complexity (20%) — lower is better
        $avgCC = $metrics->get('overallAvgCC')?->getValue() ?? 0;
        // CC 1-5 = excellent, 5-10 = good, 10-20 = moderate, >20 = poor
        $ccScore = max(0, min(100, 100 - ($avgCC - 1) * 5));
        $scores[] = $ccScore;
        $weights[] = 0.20;

        // 4. Coupling — Distance from main sequence (15%) — lower is better
        $avgDistance = abs($metrics->get('overallDistanceFromMainline')?->getValue() ?? 0);
        // Distance 0 = perfect, 1 = worst
        $couplingScore = max(0, min(100, (1 - $avgDistance) * 100));
        $scores[] = $couplingScore;
        $weights[] = 0.15;

        // 5. Code size balance (10%) — LLOC outside classes/functions should be low
        $lloc = $metrics->get('overallLloc')?->getValue() ?? 1;
        $llocOutside = $metrics->get('overallLlocOutside')?->getValue() ?? 0;
        $outsideRatio = $lloc > 0 ? $llocOutside / $lloc : 0;
        $structureScore = max(0, (1 - $outsideRatio) * 100);
        $scores[] = $structureScore;
        $weights[] = 0.10;

        // Weighted average
        $totalWeight = array_sum($weights);
        $weightedSum = 0;
        for ($i = 0; $i < count($scores); $i++) {
            $weightedSum += $scores[$i] * $weights[$i];
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
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
