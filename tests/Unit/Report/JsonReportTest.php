<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\ClassDataProvider;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\FilesDataProvider;
use PhpCodeArch\Report\DataProvider\FunctionDataProvider;
use PhpCodeArch\Report\DataProvider\GitDataProvider;
use PhpCodeArch\Report\DataProvider\ProblemDataProvider;
use PhpCodeArch\Report\DataProvider\ProjectDataProvider;
use PhpCodeArch\Report\DataProvider\TestsDataProvider;
use PhpCodeArch\Report\JsonReport;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makeJsonReportDeps(): array
{
    $tmpDir = sys_get_temp_dir() . '/pca-json-report-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $config = new Config();
    $config->set('reportType', 'json');
    $config->set('reportDir', $tmpDir);

    $projectProvider = Mockery::mock(ProjectDataProvider::class);
    $projectProvider->shouldReceive('getTemplateData')->andReturn([
        'createDate'  => '2026-01-01T00:00:00+00:00',
        'version'     => '2.5.0',
        'commonPath'  => '/src',
        'elements'    => [],
    ]);

    $problemProvider = Mockery::mock(ProblemDataProvider::class);
    $problemProvider->shouldReceive('getTemplateData')->andReturn([
        'fileProblems'     => [],
        'classProblems'    => [],
        'functionProblems' => [],
    ]);

    $gitProvider = Mockery::mock(GitDataProvider::class);
    $gitProvider->shouldReceive('getTemplateData')->andReturn([
        'gitTotalCommits'   => 100,
        'gitActiveAuthors'  => 3,
        'gitAnalysisPeriod' => '3 months',
        'hotspots'          => [],
    ]);

    $testsProvider = Mockery::mock(TestsDataProvider::class);
    $testsProvider->shouldReceive('getTemplateData')->andReturn([
        'stats'        => ['testRatio' => 0.5, 'testFileCount' => 5, 'productionFileCount' => 10],
        'coverageGaps' => [],
    ]);

    $filesProvider = Mockery::mock(FilesDataProvider::class);
    $filesProvider->shouldReceive('getTemplateData')->andReturn(['files' => []]);

    $classProvider = Mockery::mock(ClassDataProvider::class);
    $classProvider->shouldReceive('getTemplateData')->andReturn(['classes' => []]);

    $functionProvider = Mockery::mock(FunctionDataProvider::class);
    $functionProvider->shouldReceive('getTemplateData')->andReturn(['functions' => [], 'methods' => []]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')->andReturn($projectProvider);
    $factory->shouldReceive('getProblemDataProvider')->andReturn($problemProvider);
    $factory->shouldReceive('getGitDataProvider')->andReturn($gitProvider);
    $factory->shouldReceive('getTestsDataProvider')->andReturn($testsProvider);
    $factory->shouldReceive('getFilesDataProvider')->andReturn($filesProvider);
    $factory->shouldReceive('getClassDataProvider')->andReturn($classProvider);
    $factory->shouldReceive('getFunctionDataProvider')->andReturn($functionProvider);

    $output = Mockery::mock(CliOutput::class)->shouldIgnoreMissing();
    $output->shouldReceive('getFormatter')->andReturn(null);

    return [
        $config,
        $factory,
        false,
        Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing(),
        Mockery::mock(Environment::class)->shouldIgnoreMissing(),
        $output,
        $tmpDir,
    ];
}

afterEach(function () {
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tmpDir);
    }
});

it('generates a report.json file in the json subdirectory', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    (new JsonReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    expect(file_exists($tmpDir . '/json/report.json'))->toBeTrue();
});

it('generates valid JSON output', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    (new JsonReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $raw  = file_get_contents($tmpDir . '/json/report.json');
    $data = json_decode($raw, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($data)->not->toBeNull();
});

it('includes project, files, classes, and functions sections', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    (new JsonReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $data = json_decode(file_get_contents($tmpDir . '/json/report.json'), true);

    expect($data)->toHaveKeys(['project', 'files', 'classes', 'functions']);
});

it('includes tests section with stats fields', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    (new JsonReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $data = json_decode(file_get_contents($tmpDir . '/json/report.json'), true);

    expect($data)->toHaveKey('tests');
    expect($data['tests'])->toHaveKeys(['testRatio', 'testFileCount', 'productionFileCount']);
});

it('includes problems section', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    (new JsonReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $data = json_decode(file_get_contents($tmpDir . '/json/report.json'), true);

    expect($data)->toHaveKey('problems');
    expect($data['problems'])->toBeArray();
});

it('uses json subdirectory for output', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeJsonReportDeps();
    $this->tmpDir = $tmpDir;

    $report = new JsonReport($config, $factory, $hd, $tl, $twig, $output);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/json/');
});
