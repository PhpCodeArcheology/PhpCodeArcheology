<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;

final class PredictionRegistry
{
    /** @return list<PredictionInterface> */
    public function getPredictions(
        Config $config,
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
    ): array {
        return [
            new TooLongPrediction($reader, $writer, $registry, $config),
            new GodClassPrediction($reader, $writer, $registry, $config),
            new TooComplexPrediction($reader, $writer, $registry, $config),
            new TooDependentPrediction($reader, $writer, $registry, $config),
            new TooMuchHtmlPrediction($reader, $writer, $registry, $config),
            new LowTypeCoveragePrediction($reader, $writer, $registry, $config),
            new DeepInheritancePrediction($reader, $writer, $registry, $config),
            new DependencyCyclePrediction($reader, $writer, $registry, $config),
            new TooManyParametersPrediction($reader, $writer, $registry, $config),
            new DeadCodePrediction($reader, $writer, $registry),
            new SecuritySmellPrediction($reader, $writer, $registry),
            new SolidViolationPrediction($reader, $writer, $registry),
            new UntestedComplexCodePrediction($reader, $writer, $registry, $config),
            new HotspotPrediction($reader, $writer, $registry, $config),
        ];
    }
}
