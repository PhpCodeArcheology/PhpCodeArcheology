<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

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
                    $classTooComplex = $avgMethodCc > 10;

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
                            'predictionTooComplex' => $classTooComplex,
                        ],
                    );

                    if ($avgMethodCc > 10) {
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
                $maxComplexity = $values['lloc'] > 20 ? 20 : 10;
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
                return $value > 20;
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

        $problemCount += $this->check(
            'maintainabilityIndex',
            $values['maintainabilityIndex'],
            function($value) use ($avgMi) {
                $min = $avgMi - $avgMi * 0.2;

                return $value < $min;
            },
            $metricsController,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Maintainability index is more than 20% below average MI (' . number_format($avgMi) . ').'
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
