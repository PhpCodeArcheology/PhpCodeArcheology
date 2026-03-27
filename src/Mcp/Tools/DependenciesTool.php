<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class DependenciesTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getDependencies(string $class_name, string $direction = 'both'): string
    {
        try {
            $data = $this->factory->getClassCouplingDataProvider()->getTemplateData();
            $classesRaw = $data['classes'] ?? [];
            $classes = is_array($classesRaw) ? $classesRaw : [];

            $found = null;
            foreach ($classes as $collection) {
                if (!$collection instanceof MetricsCollectionInterface) {
                    continue;
                }
                $name = $collection->getString(MetricKey::FULL_NAME);
                $singleName = $collection->getString(MetricKey::SINGLE_NAME);

                if (
                    0 === strcasecmp($name, $class_name)
                    || 0 === strcasecmp($singleName, $class_name)
                    || false !== stripos($name, $class_name)
                    || false !== stripos($singleName, $class_name)
                ) {
                    $found = $collection;
                    break;
                }
            }

            if (null === $found) {
                return "Class '{$class_name}' not found.";
            }

            $fullName = $found->getString(MetricKey::FULL_NAME);
            $singleName = $found->getString(MetricKey::SINGLE_NAME) ?: $fullName;
            $usesCount = $found->getInt(MetricKey::USES_COUNT);
            $usedByCount = $found->getInt(MetricKey::USED_BY_COUNT);
            $instability = $found->getFloat(MetricKey::INSTABILITY);
            $usesInProject = $found->getArray(MetricKey::USES_IN_PROJECT);
            $usedBy = $found->getArray(MetricKey::USED_BY);

            $lines = [
                "# Dependencies: {$singleName}",
                "Full name: {$fullName}",
                '',
                '## Coupling Metrics',
                "Outgoing dependencies (uses):   {$usesCount}",
                "Incoming dependencies (usedBy): {$usedByCount}",
                'Instability:                    '.round($instability, 4),
                '',
            ];

            if ('outgoing' === $direction || 'both' === $direction) {
                $count = count($usesInProject);
                $lines[] = "## Outgoing Dependencies (in-project: {$count})";
                if (empty($usesInProject)) {
                    $lines[] = '  (none)';
                } else {
                    foreach ($usesInProject as $dep) {
                        $depStr = is_scalar($dep) ? (string) $dep : '';
                        $lines[] = "  - {$depStr}";
                    }
                }
                $lines[] = '';
            }

            if ('incoming' === $direction || 'both' === $direction) {
                $count = count($usedBy);
                $lines[] = "## Incoming Dependencies (used by: {$count})";
                if (empty($usedBy)) {
                    $lines[] = '  (none)';
                } else {
                    foreach ($usedBy as $dep) {
                        $depStr = is_scalar($dep) ? (string) $dep : '';
                        $lines[] = "  - {$depStr}";
                    }
                }
                $lines[] = '';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving dependencies.';
        }
    }
}
