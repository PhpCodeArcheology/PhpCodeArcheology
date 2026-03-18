<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class MaintainabilityIndexCalculator implements CalculatorInterface
{
    public function __construct(
        private readonly MetricsController $metricsController,
    ) {
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof FileMetricsCollection
            && !$metrics instanceof ClassMetricsCollection
            && !$metrics instanceof FunctionMetricsCollection) {
            return;
        }

        $this->metricsController->setMetricValuesByIdentifierString(
            (string) $metrics->getIdentifier(),
            $this->calculateIndex($metrics)
        );
    }

    private function calculateIndex(MetricsCollectionInterface $metric): array
    {
        $volume = $metric->get('volume')?->getValue() ?? 0;
        $cc = $metric->get('cc')?->getValue() ?? 0;

        $loc = $metric->get('loc')?->getValue() ?? 0;
        $cloc = $metric->get('cloc')?->getValue() ?? 0;
        $lloc = $metric->get('lloc')?->getValue() ?? 0;

        if ($volume == 0 || $lloc == 0) {
            return [
                'maintainabilityIndex' => 171,
                'maintainabilityIndexWithoutComments' => 50,
                'commentWeight' => 0,
            ];
        }

        $maintainabilityIndexWithoutComments = max((171
            - 5.2 * log($volume)
            - 0.23 * $cc
            - 16.2 * log($lloc)),
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
            'maintainabilityIndex' => $maintainabilityIndex,
            'maintainabilityIndexWithoutComments' => $maintainabilityIndexWithoutComments,
            'commentWeight' => $commentWeight,
        ];
    }
}
