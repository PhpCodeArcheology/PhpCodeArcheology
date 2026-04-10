<?php

declare(strict_types=1);

return [
    [
        'key' => 'classInfo',
        'name' => 'Class',
        'shortName' => '',
        'description' => 'Fully qualified name of the class this method belongs to',
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
        'description' => 'Whether this method has protected visibility',
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
        'description' => 'Whether this method has public visibility',
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
        'description' => 'Whether this method has private visibility',
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
        'description' => 'Whether this method is static',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Bool,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowNowhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::MethodCollection,
        ],
    ],
    ['key' => 'docBlockSummary', 'type' => 'storage'],
    ['key' => 'startLine', 'type' => 'storage'],
    ['key' => 'endLine', 'type' => 'storage'],
    ['key' => 'nestingDepthMap', 'type' => 'storage'],
    ['key' => 'sourceFile', 'type' => 'storage'],
];
