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
        'description' => 'Abstractness.',
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
        'description' => 'Distance.',
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
        'description' => 'Uses count.',
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
        'description' => 'Used by count.',
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
        'description' => '',
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
        'description' => '',
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
