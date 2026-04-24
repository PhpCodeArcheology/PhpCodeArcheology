<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\AnalysisConfigInterface;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\AbstractProblem;

trait PredictionTrait
{
    protected readonly MetricsReaderInterface $reader;
    protected readonly MetricsWriterInterface $writer;
    protected readonly MetricsRegistryInterface $registry;

    private ?AnalysisConfigInterface $config = null;

    /**
     * @param list<string>|string           $keys
     * @param class-string<AbstractProblem> $problemClass
     */
    private function createProblem(string $identifierString, string|array $keys, string $problemClass, int $level, string $message): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            $problem = $problemClass::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: $message
            );

            $this->writer->setProblemByIdentifierString(
                identifierString: $identifierString,
                key: $key,
                problem: $problem
            );
        }
    }

    protected function shouldSkipLcom(ClassMetricsCollection $metric): bool
    {
        $id = (string) $metric->getIdentifier();

        // Hard rules: types where LCOM is structurally meaningless
        $isEnum = $metric->get(MetricKey::ENUM)?->asBool() ?? false;
        $isInterface = $metric->get(MetricKey::INTERFACE)?->asBool() ?? false;
        $isTrait = $metric->get(MetricKey::TRAIT)?->asBool() ?? false;

        if ($isEnum || $isInterface || $isTrait) {
            return true;
        }

        // Classes with 0-1 methods: LCOM is meaningless
        $methodCollection = $this->reader->getCollectionByIdentifierString($id, 'methods');
        if (!$methodCollection instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface || count($methodCollection->getAsArray()) <= 1) {
            return true;
        }

        // Pattern-based: class name
        $className = $metric->getName();
        $defaultPatterns = ['*Exception', '*Error'];
        $configPatterns = $this->threshold('lcomExclude.patterns', null);
        $patterns = is_array($configPatterns) ? $configPatterns : $defaultPatterns;

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && fnmatch($pattern, $className)) {
                return true;
            }
        }

        // Pattern-based: implemented interfaces
        $defaultInterfaces = ['EventSubscriberInterface', 'EventListenerInterface', 'ListenerInterface'];
        $configInterfaces = $this->threshold('lcomExclude.interfaces', null);
        $interfacePatterns = is_array($configInterfaces) ? $configInterfaces : $defaultInterfaces;

        $implementedInterfaces = $this->reader->getCollectionByIdentifierString($id, 'interfaces');
        if ($implementedInterfaces instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($implementedInterfaces->getAsArray() as $ifaceName) {
                if (!is_string($ifaceName) || '' === $ifaceName) {
                    continue;
                }
                $shortName = substr(strrchr($ifaceName, '\\') ?: $ifaceName, 1) ?: $ifaceName;
                foreach ($interfacePatterns as $pattern) {
                    if (is_string($pattern) && (fnmatch($pattern, $shortName) || fnmatch($pattern, $ifaceName))) {
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
        $detection = $this->getFrameworkDetection();

        return null !== $detection && $detection->doctrineDetected;
    }

    protected function isSymfonyDetected(): bool
    {
        $detection = $this->getFrameworkDetection();

        return null !== $detection && $detection->symfonyDetected;
    }

    protected function isFrameworkAdjustmentEnabled(string $adjustment): bool
    {
        if (null === $this->getFrameworkDetection()) {
            return false;
        }

        $frameworkConfig = $this->config?->get('framework');
        if (!is_array($frameworkConfig)) {
            return true;
        }
        $adjustments = $frameworkConfig['adjustments'] ?? [];
        if (!is_array($adjustments)) {
            return true;
        }
        $value = $adjustments[$adjustment] ?? true;

        return is_bool($value) ? $value : true;
    }
}
