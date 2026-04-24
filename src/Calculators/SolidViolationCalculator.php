<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class SolidViolationCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    /** @var array<string, string> className → classIdentifier */
    private array $interfaceIds = [];

    public function beforeTraverse(): void
    {
        $interfaces = $this->reader->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'interfaces'
        );

        if ($interfaces instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($interfaces->getAsArray() as $id => $name) {
                if (!is_string($id) || !is_string($name)) {
                    continue;
                }
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
        $methodCount = $metrics->getInt(MetricKey::METHOD_COUNT);
        $lcom = $metrics->getInt(MetricKey::LCOM);
        $usesCount = count($metrics->getArray(MetricKey::USES));

        if ($methodCount > 15 && $lcom > 2 && $usesCount > 8) {
            $violations[] = 'SRP';
        }

        // --- ISP Check (only for interfaces) ---
        $isInterface = $metrics->getBool(MetricKey::INTERFACE);
        if ($isInterface && $methodCount > 10) {
            $violations[] = 'ISP';
        }

        // --- DIP Check ---
        $usedClasses = $this->reader->getCollectionByIdentifierString(
            $identifierString,
            'usedClasses'
        );

        $interfaceDeps = 0;
        $concreteDeps = 0;

        if ($usedClasses instanceof \PhpCodeArch\Metrics\Model\Collections\CollectionInterface) {
            foreach ($usedClasses->getAsArray() as $usedClassName) {
                if (!is_string($usedClassName)) {
                    continue;
                }
                if (isset($this->interfaceIds[$usedClassName])) {
                    ++$interfaceDeps;
                } else {
                    ++$concreteDeps;
                }
            }
        }

        $totalDeps = $interfaceDeps + $concreteDeps;
        $dipScore = $totalDeps > 0 ? round(($interfaceDeps / $totalDeps) * 100, 2) : 100.0;

        $this->writer->setMetricValuesByIdentifierString(
            $identifierString,
            [
                MetricKey::SOLID_VIOLATIONS => $violations,
                MetricKey::SOLID_VIOLATION_COUNT => count($violations),
                MetricKey::SRP_VIOLATION => in_array('SRP', $violations),
                MetricKey::ISP_VIOLATION => in_array('ISP', $violations),
                MetricKey::DIP_SCORE => $dipScore,
            ]
        );
    }
}
