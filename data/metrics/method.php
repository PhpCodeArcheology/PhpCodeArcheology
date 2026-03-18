<?php

declare(strict_types=1);

return [
    [
        'key' => 'classInfo',
        'name' => 'Class',
        'shortName' => '',
        'description' => '',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Array,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowDetails,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
    [
        'key' => 'protected',
        'name' => 'Protected',
        'shortName' => '',
        'description' => '',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Bool,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
    [
        'key' => 'public',
        'name' => 'Public',
        'shortName' => '',
        'description' => '',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Bool,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
    [
        'key' => 'private',
        'name' => 'Private',
        'shortName' => '',
        'description' => '',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Bool,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
    [
        'key' => 'static',
        'name' => 'static',
        'shortName' => '',
        'description' => '',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Bool,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
];
