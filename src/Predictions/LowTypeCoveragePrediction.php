<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\LowTypeCoverageProblem;

class LowTypeCoveragePrediction implements PredictionInterface
{
    use PredictionTrait;

    public function __construct(?Config $config = null)
    {
        $this->config = $config;
    }

    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $typeCoverage = $metric->get('typeCoverage')?->getValue() ?? 100;

            $errorThreshold = $this->threshold('lowTypeCoverage.error', 40);
            $warningThreshold = $this->threshold('lowTypeCoverage.warning', 60);

            if ($typeCoverage < $errorThreshold) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::ERROR,
                    message: sprintf('Type coverage is critically low at %.1f%% (threshold: %d%%).', $typeCoverage, $errorThreshold)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'typeCoverage',
                    problem: $problem
                );
            } elseif ($typeCoverage < $warningThreshold) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::WARNING,
                    message: sprintf('Type coverage is only %.1f%% (threshold: %d%%).', $typeCoverage, $warningThreshold)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'typeCoverage',
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
