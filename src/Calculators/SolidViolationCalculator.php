<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class SolidViolationCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    /** @var array<string, string> className → classIdentifier */
    private array $interfaceIds = [];

    public function beforeTraverse(): void
    {
        $interfaces = $this->metricsController->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'interfaces'
        );

        if ($interfaces !== null) {
            foreach ($interfaces->getAsArray() as $id => $name) {
                $this->interfaceIds[$name] = $id;
            }
        }
    }

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof ClassMetricsCollection) {
            return;
        }

        $identifierString = (string) $metrics->getIdentifier();
        $violations = [];

        // --- SRP Check ---
        $methodCount = $metrics->get('methodCount')?->getValue() ?? 0;
        $lcom = $metrics->get('lcom')?->getValue() ?? 0;
        $usesCount = count($metrics->get('uses')?->getValue() ?? []);

        if ($methodCount > 15 && $lcom > 2 && $usesCount > 8) {
            $violations[] = 'SRP';
        }

        // --- ISP Check (only for interfaces) ---
        $isInterface = $metrics->get('interface')?->getValue() ?? false;
        if ($isInterface && $methodCount > 10) {
            $violations[] = 'ISP';
        }

        // --- DIP Check ---
        $usedClasses = $this->metricsController->getCollectionByIdentifierString(
            $identifierString,
            'usedClasses'
        );

        $interfaceDeps = 0;
        $concreteDeps = 0;

        if ($usedClasses !== null) {
            foreach ($usedClasses->getAsArray() as $usedClassName) {
                if (isset($this->interfaceIds[$usedClassName])) {
                    $interfaceDeps++;
                } else {
                    $concreteDeps++;
                }
            }
        }

        $totalDeps = $interfaceDeps + $concreteDeps;
        $dipScore = $totalDeps > 0 ? round(($interfaceDeps / $totalDeps) * 100, 2) : 100.0;

        $this->metricsController->setMetricValuesByIdentifierString(
            $identifierString,
            [
                'solidViolations' => $violations,
                'solidViolationCount' => count($violations),
                'srpViolation' => in_array('SRP', $violations),
                'ispViolation' => in_array('ISP', $violations),
                'dipScore' => $dipScore,
            ]
        );
    }
}
