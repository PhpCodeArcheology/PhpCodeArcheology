<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigException;

it('stores and retrieves values', function () {
    $config = new Config();
    $config->set('key', 'value');

    expect($config->get('key'))->toBe('value');
});

it('returns null for missing keys', function () {
    $config = new Config();

    expect($config->get('nonexistent'))->toBeNull();
});

it('has() returns true for set keys', function () {
    $config = new Config();
    $config->set('present', 42);

    expect($config->has('present'))->toBeTrue();
});

it('has() returns false for missing keys', function () {
    $config = new Config();

    expect($config->has('absent'))->toBeFalse();
});

it('validate() throws on empty files', function () {
    $config = new Config();
    $config->set('files', []);

    $config->validate();
})->throws(ConfigException::class, 'No files or directories to analyze');

it('validate() throws when files key is not set', function () {
    $config = new Config();

    $config->validate();
})->throws(ConfigException::class, 'No files or directories to analyze');

it('validate() throws on non-existent paths', function () {
    $config = new Config();
    $config->set('files', ['/absolutely/nonexistent/path/xyz123']);

    $config->validate();
})->throws(ConfigException::class, 'does not exist');

it('validate() collects multiple errors', function () {
    $config = new Config();
    $config->set('files', [
        '/nonexistent/path/one',
        '/nonexistent/path/two',
    ]);

    try {
        $config->validate();
        test()->fail('Expected ConfigException was not thrown');
    } catch (ConfigException $e) {
        expect($e->getMessage())
            ->toContain('/nonexistent/path/one')
            ->toContain('/nonexistent/path/two')
            ->toContain('1.')
            ->toContain('2.');
    }
});

it('validate() suggests alternatives for misspelled paths', function () {
    // Create a temporary directory structure to test suggestions
    $tmpDir = sys_get_temp_dir() . '/phpcodearch_test_' . uniqid();
    mkdir($tmpDir . '/source', 0777, true);

    $config = new Config();
    // "sorce" is close to "source" (Levenshtein distance 1)
    $config->set('files', [$tmpDir . '/sorce']);

    try {
        $config->validate();
        test()->fail('Expected ConfigException was not thrown');
    } catch (ConfigException $e) {
        expect($e->getMessage())->toContain("Did you mean 'source'?");
    } finally {
        rmdir($tmpDir . '/source');
        rmdir($tmpDir);
    }
});

it('validate() rejects unknown report types', function () {
    // Use a real existing path so we get past the files check
    $config = new Config();
    $config->set('files', [__DIR__]);
    $config->set('reportType', 'docx');

    $config->validate();
})->throws(ConfigException::class, "Unknown report type 'docx'");
