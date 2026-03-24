<?php

declare(strict_types=1);

use PhpCodeArch\Application\ArgumentParser;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigException;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\HtmlReport;
use PhpCodeArch\Report\JsonReport;
use PhpCodeArch\Report\ReportFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// ArgumentParser: comma-separated report types

beforeEach(function () {
    $this->parser = new ArgumentParser();
});

it('parses single report type as string', function () {
    $config = $this->parser->parse(['--report-type=html']);

    expect($config->get('reportType'))->toBe('html');
});

it('parses comma-separated report types as array', function () {
    $config = $this->parser->parse(['--report-type=html,json']);

    expect($config->get('reportType'))->toBeArray()
        ->and($config->get('reportType'))->toBe(['html', 'json']);
});

it('trims whitespace in comma-separated types', function () {
    $config = $this->parser->parse(['--report-type=html, json , sarif']);

    expect($config->get('reportType'))->toBeArray()
        ->and($config->get('reportType'))->toBe(['html', 'json', 'sarif']);
});

// Config validation: single and array report types

it('validates single valid report type', function () {
    $config = new Config();
    $config->set('reportType', 'html');
    $config->set('files', [__DIR__]);
    $config->validate();

    expect(true)->toBeTrue();
});

it('validates array of valid report types', function () {
    $config = new Config();
    $config->set('reportType', ['html', 'json']);
    $config->set('files', [__DIR__]);
    $config->validate();

    expect(true)->toBeTrue();
});

it('rejects invalid type in array', function () {
    $config = new Config();
    $config->set('reportType', ['html', 'pdf']);
    $config->set('files', [__DIR__]);
    $config->validate();
})->throws(ConfigException::class, 'pdf');

// ReportFactory::createMultiple

it('createMultiple returns array of reports for multiple types', function () {
    $tmpDir = sys_get_temp_dir() . '/pca-multi-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $config = new Config();
    $config->set('reportType', ['html', 'json']);
    $config->set('reportDir', $tmpDir);

    $dpf = Mockery::mock(DataProviderFactory::class);
    $tl = Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing();
    $tw = Mockery::mock(Environment::class)->shouldIgnoreMissing();
    $out = new CliOutput();

    $reports = ReportFactory::createMultiple($config, $dpf, false, $tl, $tw, $out);

    expect($reports)->toBeArray()
        ->and($reports)->toHaveCount(2)
        ->and($reports[0])->toBeInstanceOf(HtmlReport::class)
        ->and($reports[1])->toBeInstanceOf(JsonReport::class);

    // Cleanup
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($tmpDir);
});
