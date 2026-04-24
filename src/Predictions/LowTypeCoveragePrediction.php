<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\LowTypeCoverageProblem;

class LowTypeCoveragePrediction implements PredictionInterface
{
    use PredictionTrait;

    public function __construct(
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
        ?Config $config = null,
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->registry = $registry;
        $this->config = $config;
    }

    public function predict(): int
    {
        $problemCount = 0;

        foreach ($this->registry->getAllCollections() as $metric) {
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

                $this->writer->setProblemByIdentifierString(
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

                $this->writer->setProblemByIdentifierString(
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
