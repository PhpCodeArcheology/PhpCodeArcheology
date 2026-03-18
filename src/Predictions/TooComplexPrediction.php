<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;

/**
 * Predict, if an element is too complex
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
                    )->getAsArray();

                    $problemCount += $this->handleStandardComplexity($metricsController, (string) $metric->getIdentifier(), get_class($metric));
                    $problemCount += $this->handleLcom($metricsController, (string) $metric->getIdentifier(), get_class($metric));

                    $methodCc = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $ccValue = $metricsController->getMetricValueByIdentifierString(
                            $methodKey,
                            'cc'
                        )->getValue();

                        $methodCc += $ccValue;

                        $problemCount += $this->handleStandardComplexity($metricsController, $methodKey, FunctionMetricsCollection::class);
                    }

                    $avgMethodCc = count($methodCollection) > 0 ? $methodCc / count($methodCollection) : 0;

                    // Cognitive complexity per method
                    $methodCogC = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $cogCValue = $metricsController->getMetricValueByIdentifierString(
                            $methodKey,
                            'cognitiveComplexity'
                        )?->getValue() ?? 0;

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
                                key: 'cognitiveComplexity',
                                problem: $problem
                            );
                        }
                    }
                    $avgMethodCogC = count($methodCollection) > 0 ? $methodCogC / count($methodCollection) : 0;

                    $classTooComplex = $avgMethodCc > $this->threshold('tooComplex.avgMethodCc', 10);

                    if ($classTooComplex) {
                        ++ $problemCount;

                        $problem = TooComplexProblem::ofProblemLevelAndMessage(
                            problemLevel: $this->getLevel(),
                            message: 'Class is too complex.'
                        );

                        $metricsController->setProblemByIdentifierString(
                            identifierString: (string) $metric->getIdentifier(),
                            key: 'cc',
                            problem: $problem
                        );
                    }

                    $metricsController->setMetricValuesByIdentifierString(
                        (string) $metric->getIdentifier(),
                        [
                            'avgMethodCc' => $avgMethodCc,
                            'avgMethodCogC' => $avgMethodCogC,
                            'predictionTooComplex' => $classTooComplex,
                        ],
                    );

                    if ($avgMethodCc > $this->threshold('tooComplex.avgMethodCc', 10)) {
                        $problem = TooComplexProblem::ofProblemLevelAndMessage(
                            problemLevel: $this->getLevel(),
                            message: 'Avg. method complexity is too high.'
                        );

                        $metricsController->setProblemByIdentifierString(
                            identifierString: (string) $metric->getIdentifier(),
                            key: 'avgMethodCc',
                            problem: $problem
                        );
                    }
                    break;

                case $metric instanceof FileMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $problemCount += $this->handleStandardComplexity($metricsController, (string) $metric->getIdentifier(), get_class($metric));
                break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }

    private function getMetricValues(MetricsController $metricsController, string $identifierString, array $metricKeys): array
    {
        $metricValues = $metricsController->getMetricValuesByIdentifierString(
            $identifierString,
            $metricKeys
        );

        $values = [];

        foreach ($metricKeys as $key) {
            $values[$key] = $metricValues[$key]?->getValue() ?? 0;
        }

        return $values;
    }

    private function handleLcom(MetricsController $metricsController, string $identifierString, string $metricClass): int
    {
        $className = basename(str_replace('\\', '/', $metricClass));
        $values = $this->getMetricValues($metricsController, $identifierString, [
            'lcom',
        ]);

        $problemCount = 0;

        $avgLcom = $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            sprintf('overall%sAvgLcom', $className)
        )->getValue();

        $problemCount += $this->check(
            'lcom',
            $values['lcom'],
            function($value) use ($avgLcom) {
                $max = $avgLcom + $avgLcom * 0.3;
                return $value > $max;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'LCOM is more than 30% above average LCOM (' . number_format($avgLcom,3) . ').'
        );

        return $problemCount;
    }

    private function handleStandardComplexity(MetricsController $metricsController, string $identifierString, string $metricClass): int
    {
        $className = basename(str_replace('\\', '/', $metricClass));

        $values = $this->getMetricValues($metricsController, $identifierString, [
            'lloc',
            'cc',
            'difficulty',
            'effort',
            'maintainabilityIndex',
        ]);

        $problemCount = 0;

        $problemCount += $this->check(
            'cc',
            $values['cc'],
            function($value) use ($values) {
                $maxComplexity = $values['lloc'] > 20
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

        $problemCount += $this->check(
            'difficulty',
            $values['difficulty'],
            function($value) {
                return $value > $this->threshold('tooComplex.difficulty', 20);
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
        )->getValue();

        $problemCount += $this->check(
            'effort',
            $values['effort'],
            function($value) use ($avgEffort) {
                $max = $avgEffort + $avgEffort * 0.3;

                return $value > $max;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Effort more than 30% above average effort (' . number_format($avgEffort) . ').'
        );

        $avgMi = $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            sprintf('overall%sAvgMaintainabilityIndex', $className)
        )->getValue();

        // Dynamic MI threshold: be more lenient for well-typed code
        // (high type coverage compensates for fewer comments in MI formula)
        $typeCoverage = $metricsController->getMetricValueByIdentifierString(
            $identifierString, 'typeCoverage'
        )?->getValue() ?? null;

        $miTolerance = 0.2; // default: 20% below average
        if ($typeCoverage !== null && $typeCoverage > 80) {
            $miTolerance = 0.3; // 30% below average for well-typed code
        }

        $problemCount += $this->check(
            'maintainabilityIndex',
            $values['maintainabilityIndex'],
            function($value) use ($avgMi, $miTolerance) {
                $min = $avgMi - $avgMi * $miTolerance;

                return $value < $min;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Maintainability index is more than ' . ($miTolerance * 100) . '% below average MI (' . number_format($avgMi) . ').'
        );

        return $problemCount;
    }

    public function check(string $key, mixed $value, string|\Closure $callback, MetricsController $metricsController, string $identifierString, string $problemClass, int $problemLevel, string $problemMessage): int
    {
        $isProblem = call_user_func($callback, $value);

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
