<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

class TooMuchHtmlPrediction implements PredictionInterface
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
                case $metric instanceof FileMetricsCollection:
                case $metric instanceof ClassMetricsCollection:
                case $metric instanceof FunctionMetricsCollection:
                    $maxPercentage = $metric instanceof FileMetricsCollection
                        ? $this->threshold('tooMuchHtml.filePercent', 25)
                        : $this->threshold('tooMuchHtml.classPercent', 10);
                    $maxOutput = $metric instanceof FileMetricsCollection
                        ? $this->threshold('tooMuchHtml.fileOutput', 10)
                        : $this->threshold('tooMuchHtml.classOutput', 4);

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

                    $metricsController->setMetricValuesByIdentifierString(
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
