<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

class RefactoringPriorityDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    public function gatherData(): void
    {
        $priorities = [];

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }

            $priority = $collection->get('refactoringPriority')?->getValue() ?? 0;
            if ($priority <= 0) {
                continue;
            }

            $priorities[] = [
                'id' => (string) $collection->getIdentifier(),
                'name' => $collection->get('singleName')?->getValue() ?? '',
                'fullName' => $collection->get('fullName')?->getValue() ?? '',
                'score' => $priority,
                'recommendation' => $collection->get('refactoringPriorityRecommendation')?->getValue() ?? '',
                'drivers' => $collection->get('refactoringPriorityDrivers')?->getValue() ?? [],
                'cc' => $collection->get('cc')?->getValue() ?? 0,
                'lcom' => $collection->get('lcom')?->getValue() ?? 0,
                'lloc' => $collection->get('lloc')?->getValue() ?? 0,
                'usedFromOutsideCount' => $collection->get('usedFromOutsideCount')?->getValue() ?? 0,
            ];
        }

        usort($priorities, fn($a, $b) => $b['score'] <=> $a['score']);

        // Score distribution buckets
        $distribution = ['clean' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        $totalClasses = 0;

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof ClassMetricsCollection) {
                continue;
            }
            // Skip interfaces, traits, enums
            if (($collection->get('interface')?->getValue() ?? false)
                || ($collection->get('trait')?->getValue() ?? false)
                || ($collection->get('enum')?->getValue() ?? false)) {
                continue;
            }

            $totalClasses++;
            $score = $collection->get('refactoringPriority')?->getValue() ?? 0;

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

        $this->templateData['avgPriority'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'overallAvgRefactoringPriority'
        )?->getValue() ?? 0;

        $this->templateData['maxPriority'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'overallMaxRefactoringPriority'
        )?->getValue() ?? 0;

        $this->templateData['classesNeedingRefactoring'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, 'overallClassesNeedingRefactoring'
        )?->getValue() ?? 0;
    }
}
