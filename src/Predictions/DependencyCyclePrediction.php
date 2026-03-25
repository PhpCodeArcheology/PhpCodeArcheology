<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
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

            $inCycle = $metric->get('inDependencyCycle')?->getValue() ?? false;

            if (!$inCycle) {
                continue;
            }

            ++$problemCount;

            $cycleClasses = $metric->get('dependencyCycleClasses')?->getValue() ?? [];
            $cycleLength = $metric->get('dependencyCycleLength')?->getValue() ?? 0;
            $classesPreview = implode(', ', array_slice($cycleClasses, 0, 5));

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
                key: 'inDependencyCycle',
                problem: $problem
            );
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }

    private function isDoctrineEntityRepoCycle(array $cycleClasses, int $cycleLength): bool
    {
        if (!$this->isDoctrineDetected() || !$this->isFrameworkAdjustmentEnabled('doctrineCycles')) {
            return false;
        }

        if ($cycleLength !== 2 || count($cycleClasses) !== 2) {
            return false;
        }

        $hasRepository = false;
        $hasNonRepository = false;

        foreach ($cycleClasses as $className) {
            $shortName = substr(strrchr($className, '\\') ?: $className, 1) ?: $className;
            if (fnmatch('*Repository', $shortName)) {
                $hasRepository = true;
            } else {
                $hasNonRepository = true;
            }
        }

        return $hasRepository && $hasNonRepository;
    }

    private function isDoctrineEntityCycle(array $cycleClasses): bool
    {
        if (!$this->isDoctrineDetected() || !$this->isFrameworkAdjustmentEnabled('entityCycles')) {
            return false;
        }

        foreach ($cycleClasses as $className) {
            if (!$this->looksLikeDoctrineEntity($className)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDoctrineEntity(string $className): bool
    {
        $parts = explode('\\', $className);
        foreach ($parts as $part) {
            if ($part === 'Entity' || $part === 'Model' || $part === 'Document') {
                return true;
            }
        }

        $shortName = end($parts) ?: $className;
        return fnmatch('*Repository', $shortName);
    }
}
