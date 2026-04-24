<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Predictions\Problems\HotspotProblem;

class HotspotPrediction implements PredictionInterface
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
            if (!$metric instanceof FileMetricsCollection) {
                continue;
            }

            $churn = $metric->get(MetricKey::GIT_CHURN_COUNT)?->asInt() ?? 0;
            $cc = $metric->get(MetricKey::CC)?->asInt() ?? 0;

            $minChurn = $this->threshold('hotspot.minChurn', 10);
            $minCc = $this->threshold('hotspot.minCc', 15);

            if ($churn >= $minChurn && $cc >= $minCc) {
                ++$problemCount;

                $problem = HotspotProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: sprintf(
                        'Hotspot: %d commits and CC=%d. Frequently changed and complex.',
                        $churn, $cc
                    )
                );

                $this->writer->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: MetricKey::GIT_CHURN_COUNT,
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
