<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Predictions\PredictionInterface;

class TechnicalDebtCalculator implements CalculatorInterface
{
    private float $totalDebt = 0;
    private int $totalLloc = 0;

    public function __construct(
        private readonly MetricsWriterInterface $writer,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection
            && !$metrics instanceof FileMetricsCollection) {
            return;
        }

        $lloc = $metrics->getInt(MetricKey::LLOC);
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

        $this->writer->setMetricValueByIdentifierString(
            (string) $metrics->getIdentifier(),
            MetricKey::TECHNICAL_DEBT_SCORE,
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
        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [MetricKey::OVERALL_TECHNICAL_DEBT_SCORE => $overallDebt]
        );
    }
}
