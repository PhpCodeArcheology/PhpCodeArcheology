<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class MaintainabilityIndexCalculator implements CalculatorInterface
{
    public function __construct(
        private readonly MetricsWriterInterface $writer,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof FileMetricsCollection
            && !$metrics instanceof ClassMetricsCollection
            && !$metrics instanceof FunctionMetricsCollection) {
            return;
        }

        $this->writer->setMetricValuesByIdentifierString(
            (string) $metrics->getIdentifier(),
            $this->calculateIndex($metrics)
        );
    }

    /** @return array<string, mixed> */
    private function calculateIndex(MetricsCollectionInterface $metric): array
    {
        $volume = $metric->getFloat(MetricKey::VOLUME);
        $cc = $metric->getInt(MetricKey::CC);

        $loc = $metric->getInt(MetricKey::LOC);
        $cloc = $metric->getInt(MetricKey::CLOC);
        $lloc = $metric->getInt(MetricKey::LLOC);

        if (0 == $volume || 0 == $lloc) {
            return [
                MetricKey::MAINTAINABILITY_INDEX => 171,
                MetricKey::MAINTAINABILITY_INDEX_WITHOUT_COMMENTS => 171,
                MetricKey::COMMENT_WEIGHT => 0,
            ];
        }

        $maintainabilityIndexWithoutComments = max(171
            - 5.2 * log($volume)
            - 0.23 * $cc
            - 16.2 * log($lloc),
            0
        );

        if (is_infinite($maintainabilityIndexWithoutComments)) {
            $maintainabilityIndexWithoutComments = 171;
        }

        $commentWeight = 0;
        if ($loc > 0) {
            $commentWeight = $cloc / $loc;
            $commentWeight = 50 * sin(sqrt(2.4 * $commentWeight));
        }

        $maintainabilityIndex = $maintainabilityIndexWithoutComments + $commentWeight;

        return [
            MetricKey::MAINTAINABILITY_INDEX => $maintainabilityIndex,
            MetricKey::MAINTAINABILITY_INDEX_WITHOUT_COMMENTS => $maintainabilityIndexWithoutComments,
            MetricKey::COMMENT_WEIGHT => $commentWeight,
        ];
    }
}
