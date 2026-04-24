<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\SolidViolationProblem;

class SolidViolationPrediction implements PredictionInterface
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

            $violations = $metric->get(MetricKey::SOLID_VIOLATIONS)?->asArray() ?? [];
            if (empty($violations)) {
                continue;
            }

            ++$problemCount;

            $problem = SolidViolationProblem::ofProblemLevelAndMessage(
                problemLevel: $this->getLevel(),
                message: sprintf('SOLID violation(s): %s', implode(', ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', $violations)))
            );

            $this->writer->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: MetricKey::SOLID_VIOLATION_COUNT,
                problem: $problem
            );
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
