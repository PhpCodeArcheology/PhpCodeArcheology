<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Predictions;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class TooMuchHtmlPrediction implements PredictionInterface
{

    public function predict(Metrics $metrics): int
    {
        $problemCount = 0;

        foreach ($metrics->getAll() as $key => $metric) {
            switch (true) {
                case $metric instanceof FileMetrics:
                case $metric instanceof ClassMetrics:
                case $metric instanceof FunctionMetrics:
                    $maxPercentage = $metric instanceof FileMetrics ? 25 : 10;
                    $maxOutput = $metric instanceof FileMetrics ? 10 : 4;

                    $htmlPercentage = $metric->get('loc') > 0 ? (100 / $metric->get('loc')) * $metric->get('htmlLoc') : 0;
                    $tooMuchHtml = $htmlPercentage > $maxPercentage;

                    $tooMuchOutput = $metric->get('outputCount') > $maxOutput;

                    if ($tooMuchHtml || $tooMuchOutput) {
                        ++ $problemCount;
                    }

                    /**
                     * View or defect shows that a File/Class/Function is either a view component
                     * or has a defect.
                     */
                    $viewOrDefect = $tooMuchHtml && $tooMuchOutput;

                    $metric->set('predictionTooMuchHtml', $tooMuchHtml);
                    $metric->set('predictionTooMuchOutput', $tooMuchOutput);
                    $metric->set('predictionViewOrDefect', $viewOrDefect);
                    $metric->set('htmlPercentage', $htmlPercentage);
                    break;
            }

            $metrics->set($key, $metric);
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
