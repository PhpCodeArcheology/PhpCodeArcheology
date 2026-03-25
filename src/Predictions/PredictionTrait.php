<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;

trait PredictionTrait
{
    private ?Config $config = null;

    private function createProblem(string $identifierString, string|array $keys, string $problemClass, int $level, string $message, MetricsController $metricsController): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            $problem = TooComplexProblem::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: $message
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: $identifierString,
                key: $key,
                problem: $problem
            );
        }
    }

    protected function shouldSkipLcom(MetricsController $metricsController, ClassMetricsCollection $metric): bool
    {
        $id = (string) $metric->getIdentifier();

        // Hard rules: types where LCOM is structurally meaningless
        $isEnum = $metric->get('enum')?->getValue() === true;
        $isInterface = $metric->get('interface')?->getValue() === true;
        $isTrait = $metric->get('trait')?->getValue() === true;

        if ($isEnum || $isInterface || $isTrait) {
            return true;
        }

        // Classes with 0-1 methods: LCOM is meaningless
        $methodCollection = $metricsController->getCollectionByIdentifierString($id, 'methods');
        if ($methodCollection === null || count($methodCollection->getAsArray()) <= 1) {
            return true;
        }

        // Pattern-based: class name
        $className = $metric->getName();
        $defaultPatterns = ['*Exception', '*Error'];
        $configPatterns = $this->threshold('lcomExclude.patterns', null);
        $patterns = is_array($configPatterns) ? $configPatterns : $defaultPatterns;

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $className)) {
                return true;
            }
        }

        // Pattern-based: implemented interfaces
        $defaultInterfaces = ['EventSubscriberInterface', 'EventListenerInterface', 'ListenerInterface'];
        $configInterfaces = $this->threshold('lcomExclude.interfaces', null);
        $interfacePatterns = is_array($configInterfaces) ? $configInterfaces : $defaultInterfaces;

        $implementedInterfaces = $metricsController->getCollectionByIdentifierString($id, 'interfaces');
        if ($implementedInterfaces !== null) {
            foreach ($implementedInterfaces->getAsArray() as $ifaceName) {
                if (!is_string($ifaceName) || $ifaceName === '') {
                    continue;
                }
                $shortName = substr(strrchr($ifaceName, '\\') ?: $ifaceName, 1) ?: $ifaceName;
                foreach ($interfacePatterns as $pattern) {
                    if (fnmatch($pattern, $shortName) || fnmatch($pattern, $ifaceName)) {
                        return true;
                    }
                }
            }
        }

        // Framework-aware: additional Symfony patterns where LCOM is structurally irrelevant
        if ($this->isSymfonyDetected()) {
            $frameworkPatterns = ['*Subscriber', '*Listener', '*Command', '*Handler'];
            foreach ($frameworkPatterns as $pattern) {
                $shortName = substr(strrchr($className, '\\') ?: $className, 1) ?: $className;
                if (fnmatch($pattern, $shortName)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function threshold(string $key, mixed $default): mixed
    {
        $thresholds = $this->config?->get('thresholds') ?? [];
        if (!is_array($thresholds)) {
            return $default;
        }

        $parts = explode('.', $key);
        $value = $thresholds;
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    protected function getFrameworkDetection(): ?FrameworkDetectionResult
    {
        $detection = $this->config?->get('frameworkDetection');
        return $detection instanceof FrameworkDetectionResult ? $detection : null;
    }

    protected function isDoctrineDetected(): bool
    {
        return $this->getFrameworkDetection()?->doctrineDetected ?? false;
    }

    protected function isSymfonyDetected(): bool
    {
        return $this->getFrameworkDetection()?->symfonyDetected ?? false;
    }

    protected function isFrameworkAdjustmentEnabled(string $adjustment): bool
    {
        if ($this->getFrameworkDetection() === null) {
            return false;
        }

        $frameworkConfig = $this->config?->get('framework') ?? [];
        $adjustments = $frameworkConfig['adjustments'] ?? [];

        return $adjustments[$adjustment] ?? true;
    }
}
