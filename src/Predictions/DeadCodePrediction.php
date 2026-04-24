<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DeadCodeProblem;

class DeadCodePrediction implements PredictionInterface
{
    use PredictionTrait;

    public function __construct(
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->registry = $registry;
    }

    public function predict(): int
    {
        $problemCount = 0;

        foreach ($this->registry->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $unusedCount = $metric->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT)?->asInt() ?? 0;

            if ($unusedCount > 0) {
                ++$problemCount;

                $unusedMethods = $metric->get(MetricKey::UNUSED_PRIVATE_METHODS)?->asArray() ?? [];

                $problem = DeadCodeProblem::ofProblemLevelAndMessage(
                    problemLevel: $this->getLevel(),
                    message: sprintf(
                        '%d unused private method(s): %s',
                        $unusedCount,
                        implode(', ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', array_slice($unusedMethods, 0, 5)))
                    )
                );

                $this->writer->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: MetricKey::UNUSED_PRIVATE_METHOD_COUNT,
                    problem: $problem
                );
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::INFO;
    }
}
