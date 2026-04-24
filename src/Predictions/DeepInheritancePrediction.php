<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DeepInheritanceProblem;

class DeepInheritancePrediction implements PredictionInterface
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

            $dit = $metric->get(MetricKey::DIT)?->asInt() ?? 0;

            if ($dit >= $this->threshold('deepInheritance.warning', 4)) {
                ++$problemCount;

                $level = $dit >= $this->threshold('deepInheritance.error', 6) ? PredictionInterface::ERROR : PredictionInterface::WARNING;

                $problem = DeepInheritanceProblem::ofProblemLevelAndMessage(
                    problemLevel: $level,
                    message: sprintf('Inheritance depth is %d (warning: >= 4, error: >= 6).', $dit)
                );

                $this->writer->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: MetricKey::DIT,
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
