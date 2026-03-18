<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Predictions\PredictionInterface;

class TechnicalDebtCalculator implements CalculatorInterface
{
    private float $totalDebt = 0;
    private int $totalLloc = 0;

    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection
            && !$metrics instanceof FileMetricsCollection) {
            return;
        }

        $lloc = $metrics->get('lloc')?->getValue() ?? 0;
        $debtPoints = 0;

        foreach ($metrics->getAll() as $metricValue) {
            foreach ($metricValue->getProblems() as $problem) {
                $debtPoints += match ($problem->getProblemLevel()) {
                    PredictionInterface::ERROR => 3,
                    PredictionInterface::WARNING => 1,
                    PredictionInterface::INFO => 0.5,
                    default => 0,
                };
            }
        }

        $debtPerHundredLines = $lloc > 0 ? round($debtPoints / $lloc * 100, 2) : 0;

        $this->metricsController->setMetricValueByIdentifierString(
            (string) $metrics->getIdentifier(),
            'technicalDebtScore',
            $debtPerHundredLines
        );

        $this->totalDebt += $debtPoints;
        if ($metrics instanceof FileMetricsCollection) {
            $this->totalLloc += $lloc;
        }
    }

    public function afterTraverse(): void
    {
        $overallDebt = $this->totalLloc > 0 ? round($this->totalDebt / $this->totalLloc * 100, 2) : 0;
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            ['overallTechnicalDebtScore' => $overallDebt]
        );
    }
}
