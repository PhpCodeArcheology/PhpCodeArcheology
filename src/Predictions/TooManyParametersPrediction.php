<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MethodMetrics\MethodMetricsCollection;
use PhpCodeArch\Predictions\Problems\TooManyParametersProblem;

class TooManyParametersPrediction implements PredictionInterface
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
            if (!$metric instanceof FunctionMetricsCollection && !$metric instanceof MethodMetricsCollection) {
                continue;
            }

            $paramCount = $metric->get('parameterCount')?->getValue() ?? 0;

            if ($paramCount > $this->threshold('tooManyParameters.warning', 4)) {
                ++$problemCount;

                $level = $paramCount > $this->threshold('tooManyParameters.error', 7) ? PredictionInterface::ERROR : PredictionInterface::WARNING;

                $problem = TooManyParametersProblem::ofProblemLevelAndMessage(
                    problemLevel: $level,
                    message: sprintf('Too many parameters (%d). Consider using a parameter object.', $paramCount)
                );

                $metricsController->setProblemByIdentifierString(
                    identifierString: (string) $metric->getIdentifier(),
                    key: 'parameterCount',
                    problem: $problem
                );
            }
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
