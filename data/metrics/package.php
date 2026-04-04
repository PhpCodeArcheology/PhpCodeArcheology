<?php

declare(strict_types=1);

return [
    [
        'key' => 'packageCohesion',
        'name' => 'Relational Cohesion',
        'shortName' => 'H',
        'description' => 'Relational cohesion H = (R+1)/N where R = internal relationships, N = types in package.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Float,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::High,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'abstractness',
        'name' => 'Abstractness',
        'shortName' => 'A',
        'description' => 'Ratio of abstract classes and interfaces to total classes in a package (A = abstracts / total). Ranges from 0 (fully concrete) to 1 (fully abstract).',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Float,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'distanceFromMainline',
        'name' => 'Distance from main line',
        'shortName' => 'Dist',
        'description' => 'Distance from the main sequence: |A + I - 1|. Measures the balance between abstractness and instability. 0 is ideal — the package sits on the main sequence.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Float,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usesCount',
        'name' => 'Efferent coupling',
        'shortName' => 'Efferent coupling',
        'description' => 'Efferent coupling (Ce) — number of other packages this package depends on. High values indicate the package relies on many external dependencies.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usedByCount',
        'name' => 'Afferent coupling',
        'shortName' => 'Afferent coupling',
        'description' => 'Afferent coupling (Ca) — number of other packages that depend on this package. High values indicate the package is widely used and changes have broad impact.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usesInProject',
        'name' => 'Used classes in project',
        'shortName' => 'Used classes in project',
        'description' => 'Packages that this package depends on',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Array,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usesInProjectCount',
        'name' => 'Used classes in project',
        'shortName' => 'Used classes in project',
        'description' => 'Number of packages this package depends on (efferent coupling)',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usesForInstability',
        'name' => 'Efferent coupling',
        'shortName' => 'Efferent coupling',
        'description' => 'Uses count für instability calculation.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Array,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
    [
        'key' => 'usesForInstabilityCount',
        'name' => 'Efferent coupling',
        'shortName' => 'Efferent coupling',
        'description' => 'Uses count für instability calculation.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::PackageCollection,
        ],
    ],
];
