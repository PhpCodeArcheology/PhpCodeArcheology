<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\Metrics;

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

                    $htmlPercentage = $metric->get('loc')->getValue() > 0 ? (100 / $metric->get('loc')->getValue()) * $metric->get('htmlLoc')->getValue() : 0;
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
