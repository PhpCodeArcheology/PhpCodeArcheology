<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class DependenciesTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getDependencies(string $class_name, string $direction = 'both'): string
    {
        try {
            $data = $this->factory->getClassCouplingDataProvider()->getTemplateData();
            $classes = $data['classes'] ?? [];

            $found = null;
            foreach ($classes as $collection) {
                $name = $collection->getName();
                $singleName = $collection->get('singleName')?->getValue() ?? '';

                if (
                    strcasecmp($name, $class_name) === 0
                    || strcasecmp($singleName, $class_name) === 0
                    || stripos($name, $class_name) !== false
                    || stripos($singleName, $class_name) !== false
                ) {
                    $found = $collection;
                    break;
                }
            }

            if ($found === null) {
                return "Class '{$class_name}' not found.";
            }

            $fullName = $found->getName();
            $singleName = $found->get('singleName')?->getValue() ?? $fullName;
            $usesCount = $found->get('usesCount')?->getValue() ?? 0;
            $usedByCount = $found->get('usedByCount')?->getValue() ?? 0;
            $instability = $found->get('instability')?->getValue() ?? 0;
            $usesInProject = $found->get('usesInProject')?->getValue() ?? [];
            $usedBy = $found->get('usedBy')?->getValue() ?? [];

            $lines = [
                "# Dependencies: {$singleName}",
                "Full name: {$fullName}",
                '',
                '## Coupling Metrics',
                "Outgoing dependencies (uses):   {$usesCount}",
                "Incoming dependencies (usedBy): {$usedByCount}",
                'Instability:                    ' . round((float) $instability, 4),
                '',
            ];

            if ($direction === 'outgoing' || $direction === 'both') {
                $count = count($usesInProject);
                $lines[] = "## Outgoing Dependencies (in-project: {$count})";
                if (empty($usesInProject)) {
                    $lines[] = '  (none)';
                } else {
                    foreach ($usesInProject as $dep) {
                        $lines[] = "  - {$dep}";
                    }
                }
                $lines[] = '';
            }

            if ($direction === 'incoming' || $direction === 'both') {
                $count = count($usedBy);
                $lines[] = "## Incoming Dependencies (used by: {$count})";
                if (empty($usedBy)) {
                    $lines[] = '  (none)';
                } else {
                    foreach ($usedBy as $dep) {
                        $lines[] = "  - {$dep}";
                    }
                }
                $lines[] = '';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'Error retrieving dependencies: ' . $e->getMessage();
        }
    }
}
