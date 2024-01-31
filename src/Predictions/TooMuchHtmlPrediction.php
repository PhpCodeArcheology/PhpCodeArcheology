<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Repository\RepositoryInterface;

class TooMuchHtmlPrediction implements PredictionInterface
{

    public function predict(RepositoryInterface $repository): int
    {
        $problemCount = 0;

        foreach ($repository->getAllMetricCollections() as $metric) {
            switch (true) {
                case $metric instanceof FileMetricsCollection:
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxPercentage = $metric instanceof FileMetricsCollection ? 25 : 10;
                    $maxOutput = $metric instanceof FileMetricsCollection ? 10 : 4;

                    $lloc = $metric->get('lloc')?->getValue() ?? 0;
                    $htmlLoc = $metric->get('htmlLoc')?->getValue() ?? 0;
                    $outputCount = $metric->get('outputCount')?->getValue() ?? 0;

                    $htmlPercentage = $lloc > 0 ? (100 / $lloc) * $htmlLoc : 0;
                    $tooMuchHtml = $htmlPercentage > $maxPercentage;

                    $outputCount = $outputCount ?? 0;
                    $tooMuchOutput = $outputCount > $maxOutput;

                    if ($tooMuchHtml || $tooMuchOutput) {
                        ++ $problemCount;
                    }

                    /**
                     * View or defect shows that a File/Class/Function is either a view component
                     * or has a defect.
                     */
                    $viewOrDefect = $tooMuchHtml && $tooMuchOutput;

                    $repository->saveMetricValues(
                        null,
                        (string) $metric->getIdentifier(),
                        [
                            'predictionTooMuchHtml' => $tooMuchHtml,
                            'predictionTooMuchOutput' => $tooMuchOutput,
                            'predictionViewOrDefect' => $viewOrDefect,
                            'htmlPercentage' => $htmlPercentage,
                        ]
                    );
                    break;
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
