<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\SecuritySmellProblem;

class SecuritySmellPrediction implements PredictionInterface
{
    use PredictionTrait;

    private const ERROR_PATTERNS = ['eval()', 'exec()', 'system()', 'shell_exec()', 'passthru()', 'proc_open()', 'popen()', 'pcntl_exec()'];

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
            if (!$metric instanceof FileMetricsCollection
                && !$metric instanceof ClassMetricsCollection
                && !$metric instanceof FunctionMetricsCollection) {
                continue;
            }

            $smellCount = $metric->get(MetricKey::SECURITY_SMELL_COUNT)?->asInt() ?? 0;
            if (0 === $smellCount) {
                continue;
            }

            $smells = $metric->get(MetricKey::SECURITY_SMELLS)?->asArray() ?? [];

            // Determine severity: if any dangerous function → ERROR, else WARNING
            $hasError = false;
            foreach ($smells as $smell) {
                foreach (self::ERROR_PATTERNS as $pattern) {
                    if (is_string($smell) && str_contains($smell, rtrim($pattern, '()'))) {
                        $hasError = true;
                        break 2;
                    }
                }
            }

            $level = $hasError ? PredictionInterface::ERROR : PredictionInterface::WARNING;

            $problem = SecuritySmellProblem::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: sprintf('%d security smell(s): %s', $smellCount, implode('; ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', array_slice($smells, 0, 3))))
            );

            $this->writer->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: MetricKey::SECURITY_SMELL_COUNT,
                problem: $problem
            );

            ++$problemCount;
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
