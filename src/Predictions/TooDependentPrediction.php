<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooDependentProblem;

class TooDependentPrediction implements PredictionInterface
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
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxDependency = $metric instanceof FunctionMetricsCollection
                        ? $this->threshold('tooDependent.function', 10)
                        : $this->threshold('tooDependent.class', 20);

                    // Framework-aware: raise threshold for Symfony/Laravel controllers
                    $frameworkDetection = $this->getFrameworkDetection();
                    if ($metric instanceof ClassMetricsCollection
                        && $this->isFrameworkAdjustmentEnabled('controllerThresholds')
                        && ($this->isSymfonyDetected() || (null !== $frameworkDetection && $frameworkDetection->laravelDetected))
                        && fnmatch('*Controller', $metric->getName())
                    ) {
                        $maxDependency = $this->threshold('tooDependent.controller', 35);
                    }

                    $tooDependent = ($metric->get(MetricKey::USES_COUNT)?->asInt() ?? 0) > $maxDependency;

                    $this->writer->setMetricValueByIdentifierString(
                        (string) $metric->getIdentifier(),
                        MetricKey::PREDICTION_TOO_DEPENDENT,
                        $tooDependent
                    );

                    if (!$tooDependent) {
                        break;
                    }

                    $maxDependencyStr = is_scalar($maxDependency) ? strval($maxDependency) : '?';
                    $problem = TooDependentProblem::ofProblemLevelAndMessage(
                        problemLevel: $this->getLevel(),
                        message: 'The element maybe is too dependent, exceeding '.$maxDependencyStr.' dependencies.'
                    );

                    $this->writer->setProblemByIdentifierString(
                        identifierString: (string) $metric->getIdentifier(),
                        key: MetricKey::USES,
                        problem: $problem
                    );

                    ++$problemCount;
                    break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::INFO;
    }
}
