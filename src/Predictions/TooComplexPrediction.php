<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;
use PhpCodeArch\Repository\RepositoryInterface;

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

    public function predict(RepositoryInterface $repository): int
    {
        $problemCount = 0;

        foreach ($repository->getAllMetricCollections() as $metric) {
            switch (true) {
                case $metric instanceof ClassMetricsCollection:
                    $methodCollection = $repository->loadCollection(
                        null,
                        (string) $metric->getIdentifier(),
                        'methods'
                    )->getAsArray();

                    $problemCount += $this->handleStandardComplexity($repository, (string) $metric->getIdentifier(), get_class($metric));
                    $problemCount += $this->handleLcom($repository, (string) $metric->getIdentifier(), get_class($metric));

                    $methodCc = 0;
                    foreach ($methodCollection as $methodKey => $methodName) {
                        $ccValue = $repository->loadMetricValue(
                            null,
                            $methodKey,
                            'cc'
                        )->getValue();

                        $methodCc += $ccValue;

                        $problemCount += $this->handleStandardComplexity($repository, $methodKey, FunctionMetricsCollection::class);
                    }

                    $avgMethodCc = count($methodCollection) > 0 ? $methodCc / count($methodCollection) : 0;
                    $classTooComplex = $avgMethodCc > 10;

                    if ($classTooComplex) {
                        ++ $problemCount;

                        $problem = TooComplexProblem::ofProblemLevelAndMessage(
                            problemLevel: $this->getLevel(),
                            message: 'Class is too complex.'
                        );

                        $repository->saveProblem(
                            (string) $metric->getIdentifier(),
                            'cc',
                            $problem
                        );
                    }

                    $repository->saveMetricValues(
                        null,
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

                        $repository->saveProblem(
                            (string) $metric->getIdentifier(),
                            'avgMethodCc',
                            $problem
                        );
                    }
                    break;

                case $metric instanceof FileMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $problemCount += $this->handleStandardComplexity($repository, (string) $metric->getIdentifier(), get_class($metric));
                break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }

    private function getMetricValues(RepositoryInterface $repository, string $identifierString, array $metricKeys): array
    {
        $metricValues = $repository->loadMetricValues(
            null,
            $identifierString,
            $metricKeys
        );

        $values = [];

        foreach ($metricKeys as $key) {
            $values[$key] = $metricValues[$key]?->getValue() ?? 0;
        }

        return $values;
    }

    private function handleLcom(RepositoryInterface $repository, string $identifierString, string $metricClass): int
    {
        $className = basename(str_replace('\\', '/', $metricClass));
        $values = $this->getMetricValues($repository, $identifierString, [
            'lcom',
        ]);

        $problemCount = 0;

        $avgLcom = $repository->loadMetricValue(
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
            $repository,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'LCOM is more than 30% above average LCOM (' . number_format($avgLcom,3) . ').'
        );

        return $problemCount;
    }

    private function handleStandardComplexity(RepositoryInterface $repository, string $identifierString, string $metricClass): int
    {
        $className = basename(str_replace('\\', '/', $metricClass));

        $values = $this->getMetricValues($repository, $identifierString, [
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
            $repository,
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
            $repository,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Difficulty is too high.'
        );

        $avgEffort = $repository->loadMetricValue(
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
            $repository,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Effort more than 30% above average effort (' . number_format($avgEffort) . ').'
        );

        $avgMi = $repository->loadMetricValue(
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
            $repository,
            $identifierString,
            TooComplexProblem::class,
            $this->getLevel(),
            'Maintainability index is more than 20% below average MI (' . number_format($avgMi) . ').'
        );

        return $problemCount;
    }

    public function check(string $key, mixed $value, string|\Closure $callback, RepositoryInterface $repository, string $identifierString, string $problemClass, int $problemLevel, string $problemMessage): int
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
            $repository
        );

        return 1;
    }

}
