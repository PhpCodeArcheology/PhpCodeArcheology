<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Predictions\Problems\UntestedComplexCodeProblem;

class UntestedComplexCodePrediction implements PredictionInterface
{
    use PredictionTrait;

    public function __construct(?Config $config = null)
    {
        $this->config = $config;
    }

    public function predict(MetricsController $metricsController): int
    {
        $frameworkDetection = $this->getFrameworkDetection();
        $testScanResult = $this->config?->get('testScanResult');

        $hasTestInfrastructure = ($frameworkDetection !== null && $frameworkDetection->hasTestFramework())
            || ($testScanResult !== null && !empty($testScanResult->testDirectories ?? []));

        if (!$hasTestInfrastructure) {
            return 0;
        }

        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $isInterface = $metric->get('interface')?->getValue() === true;
            $isTrait = $metric->get('trait')?->getValue() === true;
            $isEnum = $metric->get('enum')?->getValue() === true;
            $isAbstract = $metric->get('abstract')?->getValue() === true;

            if ($isInterface || $isTrait || $isEnum || $isAbstract) {
                continue;
            }

            $hasTest = $metric->get('hasTest')?->getValue() ?? false;
            $cc = $metric->get('cc')?->getValue() ?? 0;

            if ($hasTest) {
                continue;
            }

            if ($cc < $this->threshold('untestedComplexCode.cc', 8)) {
                continue;
            }

            $className = $metric->getName();

            $problem = UntestedComplexCodeProblem::ofProblemLevelAndMessage(
                problemLevel: $this->getLevel(),
                message: sprintf(
                    'Class %s has cyclomatic complexity of %d but no mapped test.',
                    $className,
                    $cc
                )
            );

            $metricsController->setProblemByIdentifierString(
                identifierString: (string) $metric->getIdentifier(),
                key: 'hasTest',
                problem: $problem
            );

            ++$problemCount;
        }

        return $problemCount;
    }

    public function getLevel(): int
    {
        return PredictionInterface::WARNING;
    }
}
