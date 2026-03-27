<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricKey;
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

            $typeCoverage = $metric->get(MetricKey::TYPE_COVERAGE)?->asFloat() ?? 100;

            $errorThreshold = $this->threshold('lowTypeCoverage.error', 40);
            $warningThreshold = $this->threshold('lowTypeCoverage.warning', 60);
            $errorThresholdInt = is_scalar($errorThreshold) ? intval($errorThreshold) : 40;
            $warningThresholdInt = is_scalar($warningThreshold) ? intval($warningThreshold) : 60;

            if ($typeCoverage < $errorThreshold) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::ERROR,
                    message: sprintf('Type coverage is critically low at %.1f%% (threshold: %d%%).', $typeCoverage, $errorThresholdInt)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: MetricKey::TYPE_COVERAGE,
                    problem: $problem
                );
            } elseif ($typeCoverage < $warningThreshold) {
                ++$problemCount;

                $problem = LowTypeCoverageProblem::ofProblemLevelAndMessage(
                    problemLevel: PredictionInterface::WARNING,
                    message: sprintf('Type coverage is only %.1f%% (threshold: %d%%).', $typeCoverage, $warningThresholdInt)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: MetricKey::TYPE_COVERAGE,
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
