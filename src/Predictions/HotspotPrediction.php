<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Predictions\Problems\HotspotProblem;

class HotspotPrediction implements PredictionInterface
{
    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof FileMetricsCollection) {
                continue;
            }

            $churn = $metric->get('gitChurnCount')?->getValue() ?? 0;
            $cc = $metric->get('cc')?->getValue() ?? 0;

            if ($churn >= 10 && $cc >= 15) {
                ++$problemCount;

                $problem = HotspotProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: sprintf(
                        'Hotspot: %d commits and CC=%d. Frequently changed and complex.',
                        $churn, $cc
                    )
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'gitChurnCount',
                    problem: $problem
                );
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
