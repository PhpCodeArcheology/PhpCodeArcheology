<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricKey;
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

        $hasTestInfrastructure = ($frameworkDetection instanceof \PhpCodeArch\Application\Service\FrameworkDetectionResult && $frameworkDetection->hasTestFramework())
            || ($testScanResult instanceof TestScanResult && !empty($testScanResult->testDirectories));

        if (!$hasTestInfrastructure) {
            return 0;
        }

        $problemCount = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof ClassMetricsCollection) {
                continue;
            }

            $isInterface = $metric->get(MetricKey::INTERFACE)?->asBool() ?? false;
            $isTrait = $metric->get(MetricKey::TRAIT)?->asBool() ?? false;
            $isEnum = $metric->get(MetricKey::ENUM)?->asBool() ?? false;
            $isAbstract = $metric->get(MetricKey::ABSTRACT)?->asBool() ?? false;

            if ($isInterface || $isTrait || $isEnum || $isAbstract) {
                continue;
            }

            // Classes outside phpunit.xml's <source> scope are not meant to be tested.
            $sourceExcluded = $metric->get(MetricKey::EXCLUDED_BY_PHPUNIT_SOURCE)?->asBool() ?? false;
            if ($sourceExcluded) {
                continue;
            }

            $hasTest = $metric->get(MetricKey::HAS_TEST)?->asBool() ?? false;
            $cc = $metric->get(MetricKey::CC)?->asInt() ?? 0;

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
                key: MetricKey::HAS_TEST,
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
