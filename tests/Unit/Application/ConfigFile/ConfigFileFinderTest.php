<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;

function createTmpDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/phpcodearch_cfgtest_' . uniqid();
    mkdir($tmpDir);
    return $tmpDir;
}

function cleanupTmpDir(string $tmpDir): void
{
    $files = glob($tmpDir . '/*');
    if (is_array($files)) {
        array_map('unlink', $files);
    }
    rmdir($tmpDir);
}

function createFinderWithDir(string $tmpDir): ConfigFileFinder
{
    $config = new Config();
    $config->set('files', ['/some/path']);
    $config->set('runningDir', $tmpDir);
    return new ConfigFileFinder($config);
}

it('returns false when no config file exists', function () {
    $tmpDir = createTmpDir();

    $finder = createFinderWithDir($tmpDir);
    $result = $finder->checkRunningDir();

    expect($result)->toBeFalse();

    cleanupTmpDir($tmpDir);
});

it('loads php-codearch-config.yaml when it exists', function () {
    $tmpDir = createTmpDir();
    file_put_contents($tmpDir . '/php-codearch-config.yaml', "include:\n  - src\n");

    $finder = createFinderWithDir($tmpDir);
    $result = $finder->checkRunningDir();

    expect($result)->toBeTrue();

    cleanupTmpDir($tmpDir);
});

it('loads php-codearch-config.yaml.dist as fallback', function () {
    $tmpDir = createTmpDir();
    file_put_contents($tmpDir . '/php-codearch-config.yaml.dist', "include:\n  - lib\n");

    $finder = createFinderWithDir($tmpDir);
    $result = $finder->checkRunningDir();

    expect($result)->toBeTrue();

    cleanupTmpDir($tmpDir);
});

it('prefers yaml over yaml.dist when both exist', function () {
    $tmpDir = createTmpDir();
    file_put_contents($tmpDir . '/php-codearch-config.yaml', "include:\n  - src\n");
    file_put_contents($tmpDir . '/php-codearch-config.yaml.dist', "include:\n  - lib\n");

    $finder = createFinderWithDir($tmpDir);

    // Should not throw - having both yaml and yaml.dist is the normal workflow
    $result = $finder->checkRunningDir();

    expect($result)->toBeTrue();

    cleanupTmpDir($tmpDir);
});

it('does not throw exception when both yaml and yaml.dist exist', function () {
    $tmpDir = createTmpDir();
    file_put_contents($tmpDir . '/php-codearch-config.yaml', "include:\n  - src\n");
    file_put_contents($tmpDir . '/php-codearch-config.yaml.dist', "include:\n  - lib\n");

    $finder = createFinderWithDir($tmpDir);

    expect(fn () => $finder->checkRunningDir())->not->toThrow(\Exception::class);

    cleanupTmpDir($tmpDir);
});
