<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\DependencyCycleProblem;

class DependencyCyclePrediction implements PredictionInterface
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

            $inCycle = $metric->get(MetricKey::IN_DEPENDENCY_CYCLE)?->asBool() ?? false;

            if (!$inCycle) {
                continue;
            }

            ++$problemCount;

            $cycleClasses = $metric->get(MetricKey::DEPENDENCY_CYCLE_CLASSES)?->asArray() ?? [];
            $cycleLength = $metric->get(MetricKey::DEPENDENCY_CYCLE_LENGTH)?->asInt() ?? 0;
            $classesPreview = implode(', ', array_map(fn (mixed $v): string => is_scalar($v) ? strval($v) : '', array_slice($cycleClasses, 0, 5)));

            $level = $this->getLevel();
            $message = sprintf(
                'Part of a circular dependency (cycle length: %d, classes: %s).',
                $cycleLength,
                $classesPreview
            );

            if ($this->isDoctrineEntityRepoCycle($cycleClasses, $cycleLength)) {
                $level = PredictionInterface::INFO;
                $message = sprintf(
                    'Doctrine Entity/Repository cycle (expected ORM pattern, length: %d, classes: %s).',
                    $cycleLength,
                    $classesPreview
                );
            } elseif ($this->isDoctrineEntityCycle($cycleClasses)) {
                $level = PredictionInterface::INFO;
                $message = sprintf(
                    'Doctrine Entity relationship cycle (expected ORM pattern, length: %d, classes: %s).',
                    $cycleLength,
                    $classesPreview
                );
            }

            $problem = DependencyCycleProblem::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: $message
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: MetricKey::IN_DEPENDENCY_CYCLE,
                problem: $problem
            );
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }

    /**
     * @param array<mixed> $cycleClasses
     */
    private function isDoctrineEntityRepoCycle(array $cycleClasses, int $cycleLength): bool
    {
        if (!$this->isDoctrineDetected() || !$this->isFrameworkAdjustmentEnabled('doctrineCycles')) {
            return false;
        }

        if (2 !== $cycleLength || 2 !== count($cycleClasses)) {
            return false;
        }

        $hasRepository = false;
        $hasNonRepository = false;

        foreach ($cycleClasses as $className) {
            $classNameStr = is_string($className) ? $className : '';
            $shortName = substr(strrchr($classNameStr, '\\') ?: $classNameStr, 1) ?: $classNameStr;
            if (fnmatch('*Repository', $shortName)) {
                $hasRepository = true;
            } else {
                $hasNonRepository = true;
            }
        }

        return $hasRepository && $hasNonRepository;
    }

    /**
     * @param array<mixed> $cycleClasses
     */
    private function isDoctrineEntityCycle(array $cycleClasses): bool
    {
        if (!$this->isDoctrineDetected() || !$this->isFrameworkAdjustmentEnabled('entityCycles')) {
            return false;
        }

        foreach ($cycleClasses as $className) {
            if (!is_string($className) || !$this->looksLikeDoctrineEntity($className)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDoctrineEntity(string $className): bool
    {
        $parts = explode('\\', $className);
        foreach ($parts as $part) {
            if ('Entity' === $part || 'Model' === $part || 'Document' === $part) {
                return true;
            }
        }

        $shortName = end($parts) ?: $className;

        return fnmatch('*Repository', $shortName);
    }
}
