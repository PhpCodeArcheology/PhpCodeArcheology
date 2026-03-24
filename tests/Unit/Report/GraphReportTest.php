<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\GraphDataProvider;
use PhpCodeArch\Report\GraphReport;
use PhpCodeArch\Report\ReportFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makeGraphReportDeps(): array
{
    $tmpDir = sys_get_temp_dir() . '/pca-graph-report-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $config = new Config();
    $config->set('reportType', 'graph');
    $config->set('reportDir', $tmpDir);

    $graphDataProvider = Mockery::mock(GraphDataProvider::class);
    $graphDataProvider->shouldReceive('gatherData');
    $graphDataProvider->shouldReceive('getGraphData')->andReturn([
        'nodes'    => [],
        'edges'    => [],
        'clusters' => [],
        'cycles'   => [],
    ]);

    $dataProviderFactory = Mockery::mock(DataProviderFactory::class);
    $dataProviderFactory->shouldReceive('getGraphDataProvider')->andReturn($graphDataProvider);

    $twigLoader = Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing();
    $twig       = Mockery::mock(Environment::class)->shouldIgnoreMissing();
    $output     = new CliOutput();

    return [$config, $dataProviderFactory, false, $twigLoader, $twig, $output, $tmpDir];
}

afterEach(function () {
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tmpDir);
    }
});

// ── Subdirectory ──────────────────────────────────────────────────────────────

it('GraphReport uses graph subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = makeGraphReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new GraphReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/graph/');
});

// ── generate() ────────────────────────────────────────────────────────────────

it('generate() writes graph.json into the graph subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = makeGraphReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new GraphReport($config, $dpf, $hd, $tl, $tw, $out);
    $report->generate();

    expect(file_exists($tmpDir . '/graph/graph.json'))->toBeTrue();
});

it('generate() writes valid JSON', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = makeGraphReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new GraphReport($config, $dpf, $hd, $tl, $tw, $out);
    $report->generate();

    $raw = file_get_contents($tmpDir . '/graph/graph.json');
    $data = json_decode($raw, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($data)->not->toBeNull();
});

it('generate() writes JSON with required top-level keys', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = makeGraphReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new GraphReport($config, $dpf, $hd, $tl, $tw, $out);
    $report->generate();

    $data = json_decode(file_get_contents($tmpDir . '/graph/graph.json'), true);

    expect($data)->toHaveKeys(['version', 'generatedAt', 'nodes', 'edges', 'clusters', 'cycles']);
    expect($data['version'])->toBe('1.0');
});

it('generate() includes generatedAt as ISO 8601 timestamp', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = makeGraphReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new GraphReport($config, $dpf, $hd, $tl, $tw, $out);
    $report->generate();

    $data = json_decode(file_get_contents($tmpDir . '/graph/graph.json'), true);

    // DateTimeInterface::ATOM format: e.g. 2026-03-24T10:00:00+00:00
    expect($data['generatedAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('generate() passes graph data nodes and edges into JSON output', function () {
    $tmpDir = sys_get_temp_dir() . '/pca-graph-report-test-' . uniqid();
    mkdir($tmpDir, 0755, true);
    $this->tmpDir = $tmpDir;

    $config = new Config();
    $config->set('reportType', 'graph');
    $config->set('reportDir', $tmpDir);

    $graphDataProvider = Mockery::mock(GraphDataProvider::class);
    $graphDataProvider->shouldReceive('gatherData');
    $graphDataProvider->shouldReceive('getGraphData')->andReturn([
        'nodes'    => [['id' => 'file:abc', 'type' => 'file', 'name' => 'Foo.php']],
        'edges'    => [['source' => 'file:abc', 'target' => 'class:xyz', 'type' => 'contains']],
        'clusters' => [],
        'cycles'   => [],
    ]);

    $dataProviderFactory = Mockery::mock(DataProviderFactory::class);
    $dataProviderFactory->shouldReceive('getGraphDataProvider')->andReturn($graphDataProvider);

    $report = new GraphReport(
        $config, $dataProviderFactory, false,
        Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing(),
        Mockery::mock(Environment::class)->shouldIgnoreMissing(),
        new CliOutput()
    );
    $report->generate();

    $data = json_decode(file_get_contents($tmpDir . '/graph/graph.json'), true);

    expect($data['nodes'])->toHaveCount(1);
    expect($data['edges'])->toHaveCount(1);
    expect($data['nodes'][0]['type'])->toBe('file');
});

// ── ReportFactory integration ─────────────────────────────────────────────────

it('ReportFactory::create() returns GraphReport for type graph', function () {
    $tmpDir = sys_get_temp_dir() . '/pca-graph-factory-test-' . uniqid();
    $this->tmpDir = $tmpDir;

    $config = new Config();
    $config->set('reportType', 'graph');
    $config->set('reportDir', $tmpDir);

    $report = ReportFactory::create(
        $config,
        Mockery::mock(DataProviderFactory::class),
        false,
        Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing(),
        Mockery::mock(Environment::class)->shouldIgnoreMissing(),
        new CliOutput()
    );

    expect($report)->toBeInstanceOf(GraphReport::class);
});

// ── Config validation ─────────────────────────────────────────────────────────

it('Config::validate() accepts graph as a valid report type', function () {
    $config = new Config();
    $config->set('files', [__DIR__]);
    $config->set('reportType', 'graph');

    // Must not throw ConfigException
    $config->validate();

    expect(true)->toBeTrue();
});
