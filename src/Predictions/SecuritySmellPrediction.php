<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\Problems\SecuritySmellProblem;

class SecuritySmellPrediction implements PredictionInterface
{
    private const ERROR_PATTERNS = ['eval()', 'exec()', 'system()', 'shell_exec()', 'passthru()', 'proc_open()', 'popen()', 'pcntl_exec()'];

    public function predict(MetricsController $metricsController): int
    {
        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof FileMetricsCollection
                && !$metric instanceof ClassMetricsCollection
                && !$metric instanceof FunctionMetricsCollection) {
                continue;
            }

            $smellCount = $metric->get('securitySmellCount')?->getValue() ?? 0;
            if ($smellCount === 0) {
                continue;
            }

            $smells = $metric->get('securitySmells')?->getValue() ?? [];

            // Determine severity: if any dangerous function → ERROR, else WARNING
            $hasError = false;
            foreach ($smells as $smell) {
                foreach (self::ERROR_PATTERNS as $pattern) {
                    if (str_contains($smell, rtrim($pattern, '()'))) {
                        $hasError = true;
                        break 2;
                    }
                }
            }

            $level = $hasError ? PredictionInterface::ERROR : PredictionInterface::WARNING;

            $problem = SecuritySmellProblem::ofProblemLevelAndMessage(
                problemLevel: $level,
                message: sprintf('%d security smell(s): %s', $smellCount, implode('; ', array_slice($smells, 0, 3)))
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: 'securitySmellCount',
                problem: $problem
            );

            ++$problemCount;
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::ERROR;
    }
}
