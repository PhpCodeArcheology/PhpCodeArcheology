<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\AbstractProblem;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;

/**
 * Predict, if an element is too complex.
 *
 * CC: 10 - 20
 * Difficulty: 15 - 20
 * Effort: > 3000
 * MI: < 70
 * LCOM: 1,5 - 2
 */
class TooComplexPrediction implements PredictionInterface
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
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                    $methodCollection = $metricsController->getCollectionByIdentifierString(
                        (string) $metric->getIdentifier(),
                        'methods'
                    )?->getAsArray() ?? [];

                    $problemCount += $this->handleStandardComplexity($metricsController, (string) $metric->getIdentifier(), $metric::class);
                    $problemCount += $this->handleLcom($metricsController, (string) $metric->getIdentifier(), $metric::class, $metric);

                    $methodCc = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $ccValue = $metricsController->getMetricValueByIdentifierString(
                            $methodKey,
                            MetricKey::CC
                        )?->asInt() ?? 0;

                        $methodCc += $ccValue;

                        $problemCount += $this->handleStandardComplexity($metricsController, $methodKey, FunctionMetricsCollection::class);
                    }

                    $avgMethodCc = count($methodCollection) > 0 ? $methodCc / count($methodCollection) : 0;

                    // Cognitive complexity per method
                    $methodCogC = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $cogCValue = $metricsController->getMetricValueByIdentifierString(
                            $methodKey,
                            MetricKey::COGNITIVE_COMPLEXITY
                        )?->asInt() ?? 0;

                        $methodCogC += $cogCValue;

                        // Flag methods with high cognitive complexity
                        if ($cogCValue > $this->threshold('tooComplex.cognitiveComplexity', 15)) {
                            ++$problemCount;
                            $problem = TooComplexProblem::ofProblemLevelAndMessage(
                                problemLevel: $this->getLevel(),
                                message: 'Cognitive complexity is too high.'
                            );
                            $metricsController->setProblemByIdentifierString(
                                identifierString: $methodKey,
                                key: MetricKey::COGNITIVE_COMPLEXITY,
                                problem: $problem
                            );
                        }
                    }
                    $avgMethodCogC = count($methodCollection) > 0 ? $methodCogC / count($methodCollection) : 0;

                    $classTooComplex = $avgMethodCc > $this->threshold('tooComplex.avgMethodCc', 10);

                    if ($classTooComplex) {
                        ++$problemCount;

                        $problem = TooComplexProblem::ofProblemLevelAndMessage(
                            problemLevel: $this->getLevel(),
                            message: 'Class is too complex.'
                        );

                        $metricsController->setProblemByIdentifierString(
                            identifierString: (string) $metric->getIdentifier(),
                            key: MetricKey::CC,
                            problem: $problem
                        );
                    }

                    $metricsController->setMetricValuesByIdentifierString(
                        (string) $metric->getIdentifier(),
                        [
                            MetricKey::AVG_METHOD_CC => $avgMethodCc,
                            MetricKey::AVG_METHOD_COG_C => $avgMethodCogC,
                            MetricKey::PREDICTION_TOO_COMPLEX => $classTooComplex,
                        ],
                    );

                    break;

                case $metric instanceof FileMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $problemCount += $this->handleStandardComplexity($metricsController, (string) $metric->getIdentifier(), $metric::class);
                    break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }

    /**
     * @param string[] $metricKeys
     *
     * @return array<string, float|int>
     */
    private function getMetricValues(MetricsController $metricsController, string $identifierString, array $metricKeys): array
    {
        $metricValues = $metricsController->getMetricValuesByIdentifierString(
            $identifierString,
            $metricKeys
        );

        $values = [];

        foreach ($metricKeys as $key) {
            $values[$key] = $metricValues[$key]?->asFloat() ?? 0;
        }

        return $values;
    }

    private function handleLcom(MetricsController $metricsController, string $identifierString, string $metricClass, ?ClassMetricsCollection $classMetric = null): int
    {
        if ($classMetric instanceof ClassMetricsCollection && $this->shouldSkipLcom($metricsController, $classMetric)) {
            return 0;
        }

        $className = basename(str_replace('\\', '/', $metricClass));
        $values = $this->getMetricValues($metricsController, $identifierString, [
            MetricKey::LCOM,
        ]);

        $problemCount = 0;

        $avgLcom = $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            sprintf('overall%sAvgLcom', $className)
        )?->asFloat() ?? 0.0;

        $rawLcomTol = $this->threshold('tooComplex.lcomRelativeTolerance', 0.30);
        $lcomTolerance = is_numeric($rawLcomTol) ? (float) $rawLcomTol : 0.30;

        return $problemCount + $this->check(
            MetricKey::LCOM,
            $values[MetricKey::LCOM],
            function ($value) use ($avgLcom, $lcomTolerance): bool {
                $max = max(1, $avgLcom) + max(1, $avgLcom) * $lcomTolerance;

                return $value > $max;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            self::WARNING,
            'LCOM is more than '.($lcomTolerance * 100).'% above average LCOM ('.number_format($avgLcom, 3).').'
        );
    }

    private function handleStandardComplexity(MetricsController $metricsController, string $identifierString, string $metricClass): int
    {
        $className = basename(str_replace('\\', '/', $metricClass));

        $values = $this->getMetricValues($metricsController, $identifierString, [
            MetricKey::LLOC,
            MetricKey::CC,
            MetricKey::DIFFICULTY,
            MetricKey::EFFORT,
            MetricKey::MAINTAINABILITY_INDEX,
        ]);

        $problemCount = 0;

        $isFrameworkProject = $this->isFrameworkAdjustmentEnabled('complexityThresholds')
            && ($this->isSymfonyDetected() || $this->getFrameworkDetection()?->laravelDetected);

        $problemCount += $this->check(
            MetricKey::CC,
            $values[MetricKey::CC],
            function ($value) use ($values): bool {
                $maxComplexity = $values[MetricKey::LLOC] > 20
                    ? $this->threshold('tooComplex.ccLargeCode', 20)
                    : $this->threshold('tooComplex.cc', 10);

                return $value > $maxComplexity;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Complexity is too high.'
        );

        $maxDifficulty = $isFrameworkProject
            ? $this->threshold('tooComplex.difficultyFramework', 45)
            : $this->threshold('tooComplex.difficulty', 30);

        $problemCount += $this->check(
            MetricKey::DIFFICULTY,
            $values[MetricKey::DIFFICULTY],
            function ($value) use ($values, $maxDifficulty): bool {
                if ($values[MetricKey::LLOC] <= $this->threshold('tooComplex.trivialLloc', 5)) {
                    return false;
                }

                return $value > $maxDifficulty;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Difficulty is too high.'
        );

        $avgEffort = $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            sprintf('overall%sAvgEffort', $className)
        )?->asFloat() ?? 0.0;

        $rawEffortTol = $isFrameworkProject
            ? $this->threshold('tooComplex.effortRelativeToleranceFramework', 0.50)
            : $this->threshold('tooComplex.effortRelativeTolerance', 0.30);
        $effortTolerance = is_numeric($rawEffortTol) ? (float) $rawEffortTol : ($isFrameworkProject ? 0.50 : 0.30);

        $problemCount += $this->check(
            MetricKey::EFFORT,
            $values[MetricKey::EFFORT],
            function ($value) use ($values, $avgEffort, $effortTolerance): bool {
                if ($values[MetricKey::LLOC] <= $this->threshold('tooComplex.trivialLloc', 5)) {
                    return false;
                }
                $max = $avgEffort + $avgEffort * $effortTolerance;

                return $value > $max;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            self::WARNING,
            'Effort more than '.($effortTolerance * 100).'% above average effort ('.number_format($avgEffort).').'
        );

        $avgMi = $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            sprintf('overall%sAvgMaintainabilityIndex', $className)
        )?->asFloat() ?? 0.0;

        // Dynamic MI threshold: be more lenient for well-typed code
        // (high type coverage compensates for fewer comments in MI formula)
        $typeCoverage = $metricsController->getMetricValueByIdentifierString(
            $identifierString, MetricKey::TYPE_COVERAGE
        )?->asFloat();

        $rawMiTol = $this->threshold('tooComplex.miRelativeTolerance', 0.20);
        $miTolerance = is_numeric($rawMiTol) ? (float) $rawMiTol : 0.20;
        if (null !== $typeCoverage && $typeCoverage > 80) {
            $rawMiTolTyped = $this->threshold('tooComplex.miRelativeToleranceTyped', 0.30);
            $miTolerance = max($miTolerance, is_numeric($rawMiTolTyped) ? (float) $rawMiTolTyped : 0.30);
        }
        if ($isFrameworkProject) {
            $rawMiTolFramework = $this->threshold('tooComplex.miRelativeToleranceFramework', 0.35);
            $miTolerance = max($miTolerance, is_numeric($rawMiTolFramework) ? (float) $rawMiTolFramework : 0.35);
        }

        return $problemCount + $this->check(
            MetricKey::MAINTAINABILITY_INDEX,
            $values[MetricKey::MAINTAINABILITY_INDEX],
            function ($value) use ($values, $avgMi, $miTolerance): bool {
                if ($values[MetricKey::LLOC] <= $this->threshold('tooComplex.trivialLloc', 5)) {
                    return false;
                }
                $min = $avgMi - $avgMi * $miTolerance;

                return $value < $min;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            self::WARNING,
            'Maintainability index is more than '.($miTolerance * 100).'% below average MI ('.number_format($avgMi).').'
        );
    }

    /**
     * @param class-string<AbstractProblem> $problemClass
     */
    public function check(string $key, mixed $value, \Closure $callback, MetricsController $metricsController, string $identifierString, string $problemClass, int $problemLevel, string $problemMessage): int
    {
        $isProblem = $callback($value);

        if (!$isProblem) {
            return 0;
        }

        $this->createProblem(
            $identifierString,
            $key,
            $problemClass,
            $problemLevel,
            $problemMessage,
            $metricsController
        );

        return 1;
    }
}
