<?php

declare(strict_types=1);

return [
    // File-level git metrics
    [
        'key' => 'gitChurnCount',
        'name' => 'Change frequency',
        'shortName' => 'Churn',
        'description' => 'Number of commits that modified this file in the analysis period.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Low,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::FileCollection,
        ],
    ],
    [
        'key' => 'gitLastModified',
        'name' => 'Last modified',
        'shortName' => 'Modified',
        'description' => 'Date of the last commit that modified this file.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::String,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowDetails,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::FileCollection,
        ],
    ],
    [
        'key' => 'gitCodeAgeDays',
        'name' => 'Code age (days)',
        'shortName' => 'Age',
        'description' => 'Days since last modification.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::FileCollection,
        ],
    ],
    [
        'key' => 'gitAuthorCount',
        'name' => 'Author count',
        'shortName' => 'Authors',
        'description' => 'Number of distinct authors who modified this file.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::FileCollection,
        ],
    ],
    [
        'key' => 'gitAuthors',
        'name' => 'Authors',
        'shortName' => '',
        'description' => 'List of authors who modified this file.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Array,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowDetails,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::FileCollection,
        ],
    ],

    // Project-level git metrics
    [
        'key' => 'gitTotalCommits',
        'name' => 'Total commits',
        'shortName' => 'Commits',
        'description' => 'Total number of commits in the analysis period.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::ProjectCollection,
        ],
    ],
    [
        'key' => 'gitActiveAuthors',
        'name' => 'Active authors',
        'shortName' => 'Authors',
        'description' => 'Number of distinct authors in the analysis period.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::Int,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowEverywhere,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::ProjectCollection,
        ],
    ],
    [
        'key' => 'gitAnalysisPeriod',
        'name' => 'Git analysis period',
        'shortName' => '',
        'description' => 'Timeframe used for git analysis.',
        'valueType' => \PhpCodeArch\Metrics\Model\Enums\MetricValueType::String,
        'better' => \PhpCodeArch\Metrics\Model\Enums\BetterDirection::Irrelevant,
        'visibility' => \PhpCodeArch\Metrics\Model\Enums\MetricVisibility::ShowDetails,
        'collections' => [
            \PhpCodeArch\Metrics\MetricCollectionTypeEnum::ProjectCollection,
        ],
    ],
];
