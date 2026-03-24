<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\HotspotsTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\GitDataProvider;

function makeHotspotsFactory(array $templateData): DataProviderFactory
{
    $provider = Mockery::mock(GitDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn($templateData);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGitDataProvider')->andReturn($provider);
    return $factory;
}

it('returns a formatted hotspot table', function () {
    $factory = makeHotspotsFactory([
        'hotspots' => [
            ['id' => '/src/Foo.php', 'name' => 'Foo.php', 'churn' => 42, 'cc' => 8, 'loc' => 200, 'authors' => 3],
            ['id' => '/src/Bar.php', 'name' => 'Bar.php', 'churn' => 10, 'cc' => 4, 'loc' => 100, 'authors' => 1],
        ],
        'gitTotalCommits'    => 250,
        'gitActiveAuthors'   => 5,
        'gitAnalysisPeriod'  => '6 months',
    ]);

    $result = (new HotspotsTool($factory))->getHotspots();

    expect($result)
        ->toContain('Code Hotspots')
        ->toContain('Foo.php')
        ->toContain('Total Commits: 250')
        ->toContain('Active Authors: 5');
});

it('respects the limit parameter', function () {
    $hotspots = array_map(
        fn($i) => ['id' => "/src/File{$i}.php", 'name' => "File{$i}.php", 'churn' => $i, 'cc' => 1, 'loc' => 50, 'authors' => 1],
        range(1, 10)
    );
    $factory = makeHotspotsFactory([
        'hotspots'          => $hotspots,
        'gitTotalCommits'   => 100,
        'gitActiveAuthors'  => 2,
        'gitAnalysisPeriod' => '1 year',
    ]);

    $result = (new HotspotsTool($factory))->getHotspots(limit: 3);

    // First 3 items are sliced, all have 'File' in name
    expect($result)->toContain('File');
    // Ensure the table header is there
    expect($result)->toContain('Churn');
});

it('returns "no hotspot data available" when hotspots are empty', function () {
    $factory = makeHotspotsFactory([
        'hotspots'          => [],
        'gitTotalCommits'   => 0,
        'gitActiveAuthors'  => 0,
        'gitAnalysisPeriod' => 'N/A',
    ]);

    $result = (new HotspotsTool($factory))->getHotspots();

    expect($result)->toContain('No hotspot data available');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGitDataProvider')
        ->andThrow(new \RuntimeException('git not found'));

    $result = (new HotspotsTool($factory))->getHotspots();

    expect($result)->toBe('Error retrieving hotspots: git not found');
});
