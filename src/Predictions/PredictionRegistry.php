<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;

final class PredictionRegistry
{
    /** @return list<PredictionInterface> */
    public function getPredictions(Config $config): array
    {
        return [
            new TooLongPrediction($config),
            new GodClassPrediction($config),
            new TooComplexPrediction($config),
            new TooDependentPrediction($config),
            new TooMuchHtmlPrediction($config),
            new LowTypeCoveragePrediction($config),
            new DeepInheritancePrediction($config),
            new DependencyCyclePrediction($config),
            new TooManyParametersPrediction($config),
            new DeadCodePrediction(),
            new SecuritySmellPrediction(),
            new SolidViolationPrediction(),
            new UntestedComplexCodePrediction($config),
            new HotspotPrediction($config),
        ];
    }
}
