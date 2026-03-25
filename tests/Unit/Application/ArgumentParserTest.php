<?php

declare(strict_types=1);

use PhpCodeArch\Application\ArgumentParser;
use PhpCodeArch\Application\ParamException;

beforeEach(function () {
    $this->parser = new ArgumentParser();
});

it('parses --report-type=json', function () {
    $config = $this->parser->parse(['--report-type=json']);

    expect($config->get('reportType'))->toBe('json');
});

it('parses --report-dir=/tmp', function () {
    $config = $this->parser->parse(['--report-dir=/tmp']);

    expect($config->get('reportDir'))->toBe('/tmp');
});

it('parses --extensions=php,inc', function () {
    $config = $this->parser->parse(['--extensions=php,inc']);

    expect($config->get('extensions'))->toBe(['php', 'inc']);
});

it('parses --quick flag', function () {
    $config = $this->parser->parse(['--quick']);

    expect($config->get('quickMode'))->toBeTrue();
});

it('parses --no-color flag', function () {
    $config = $this->parser->parse(['--no-color']);

    expect($config->get('noColor'))->toBeTrue();
});

it('detects subcommand init', function () {
    $config = $this->parser->parse(['init']);

    expect($config->get('command'))->toBe('init');
});

it('detects subcommand compare with args', function () {
    $config = $this->parser->parse(['compare', 'baseline.json', 'current.json']);

    expect($config->get('command'))->toBe('compare')
        ->and($config->get('commandArgs'))->toContain('baseline.json')
        ->and($config->get('commandArgs'))->toContain('current.json');
});

it('detects subcommand baseline with sub-args', function () {
    $config = $this->parser->parse(['baseline', '--report-type=json', 'output.json']);

    expect($config->get('command'))->toBe('baseline')
        ->and($config->get('commandArgs'))->toContain('output.json')
        ->and($config->get('reportType'))->toBe('json');
});

it('stores commandArgs separately', function () {
    $config = $this->parser->parse(['compare', 'fileA.json', 'fileB.json']);

    $commandArgs = $config->get('commandArgs');

    expect($commandArgs)->toBeArray()
        ->and($commandArgs)->toHaveCount(2)
        ->and($commandArgs[0])->toBe('fileA.json')
        ->and($commandArgs[1])->toBe('fileB.json');
});

it('defaults to src directory when no files given', function () {
    $config = $this->parser->parse(['--quick']);

    $files = $config->get('files');

    expect($files)->toBeArray()
        ->and($files[0])->toEndWith('/src');
});

it('throws on unknown parameters', function () {
    $this->parser->parse(['--foobar=baz']);
})->throws(ParamException::class, 'does not exist');

it('throws on unknown flag parameters', function () {
    $this->parser->parse(['--unknown-flag']);
})->throws(ParamException::class, 'does not exist');

it('parses --coverage-file=path (equals form)', function () {
    $config = $this->parser->parse(['--coverage-file=nonexistent-coverage.xml']);

    expect($config->get('coverageFile'))->toBe('nonexistent-coverage.xml');
});

it('parses --coverage-file path (space form)', function () {
    $config = $this->parser->parse(['--coverage-file', 'nonexistent-coverage.xml']);

    expect($config->get('coverageFile'))->toBe('nonexistent-coverage.xml');
});

it('does not merge boolean flags with next argument (--quick src/ should NOT become --quick=src/)', function () {
    $config = $this->parser->parse(['--quick', 'src/']);

    expect($config->get('quickMode'))->toBeTrue()
        ->and($config->get('files'))->toContain('src/');
});
