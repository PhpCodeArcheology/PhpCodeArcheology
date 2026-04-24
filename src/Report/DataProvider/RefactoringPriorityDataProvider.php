<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class RefactoringPriorityDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $priorities = [];

        foreach ($this->registry->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $priority = $collection->getFloat(MetricKey::REFACTORING_PRIORITY);
            if ($priority <= 0) {
                continue;
            }

            $priorities[] = [
                'id' => (string) $collection->getIdentifier(),
                'name' => $collection->getString(MetricKey::SINGLE_NAME),
                'fullName' => $collection->getString(MetricKey::FULL_NAME),
                'score' => $priority,
                'recommendation' => $collection->getString(MetricKey::REFACTORING_PRIORITY_RECOMMENDATION),
                'drivers' => $collection->getArray(MetricKey::REFACTORING_PRIORITY_DRIVERS),
                'cc' => $collection->getInt(MetricKey::CC),
                'lcom' => $collection->getFloat(MetricKey::LCOM),
                'lloc' => $collection->getInt(MetricKey::LLOC),
                'usedFromOutsideCount' => $collection->getInt(MetricKey::USED_FROM_OUTSIDE_COUNT),
            ];
        }

        usort($priorities, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        // Score distribution buckets
        $distribution = ['clean' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        $totalClasses = 0;

        foreach ($this->registry->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }
            // Skip interfaces, traits, enums
            if ($collection->getBool(MetricKey::INTERFACE)
                || $collection->getBool(MetricKey::TRAIT)
                || $collection->getBool(MetricKey::ENUM)) {
                continue;
            }

            ++$totalClasses;
            $score = $collection->getFloat(MetricKey::REFACTORING_PRIORITY);

            match (true) {
                $score <= 0 => $distribution['clean']++,
                $score <= 25 => $distribution['low']++,
                $score <= 50 => $distribution['medium']++,
                $score <= 75 => $distribution['high']++,
                default => $distribution['critical']++,
            };
        }

        $this->templateData['refactoringPriorities'] = $priorities;
        $this->templateData['distribution'] = $distribution;
        $this->templateData['totalClasses'] = $totalClasses;

        $this->templateData['avgPriority'] = $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_AVG_REFACTORING_PRIORITY
        )?->asFloat() ?? 0.0;

        $this->templateData['maxPriority'] = $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_MAX_REFACTORING_PRIORITY
        )?->asFloat() ?? 0.0;

        $this->templateData['classesNeedingRefactoring'] = $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, MetricKey::OVERALL_CLASSES_NEEDING_REFACTORING
        )?->asInt() ?? 0;
    }
}
