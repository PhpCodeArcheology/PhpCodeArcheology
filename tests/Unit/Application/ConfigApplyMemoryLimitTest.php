<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;

// Save and restore memory_limit around every test to avoid side effects.
beforeEach(function () {
    $this->originalMemoryLimit = ini_get('memory_limit');
});

afterEach(function () {
    ini_set('memory_limit', $this->originalMemoryLimit);
});

// --- Explicit config values ---

it('applies explicit memory limit "2G" via ini_set', function () {
    $config = new Config();
    $config->set('memoryLimit', '2G');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('2G');
});

it('applies "-1" (unlimited) when config is set to -1', function () {
    $config = new Config();
    $config->set('memoryLimit', '-1');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('-1');
});

// --- Invalid config value falls back to default behavior ---

it('falls back to 1G when config is invalid and php.ini is not unlimited', function () {
    ini_set('memory_limit', '128M');

    $config = new Config();
    $config->set('memoryLimit', 'invalid_value');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('1G');
});

it('does not override unlimited php.ini when config is invalid', function () {
    // Else branch: current is -1, so ini_set is skipped — stays unlimited.
    ini_set('memory_limit', '-1');

    $config = new Config();
    $config->set('memoryLimit', 'not-a-valid-limit');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('-1');
});

// --- No config set: getMemoryLimit() returns the hard-coded default '1G' ---

it('applies the default 1G when no memoryLimit is configured and php.ini is 128M', function () {
    ini_set('memory_limit', '128M');

    $config = new Config();
    // no memoryLimit key set → getMemoryLimit() returns '1G'

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('1G');
});

it('preserves unlimited php.ini when no memoryLimit is configured', function () {
    ini_set('memory_limit', '-1');

    $config = new Config();
    // no memoryLimit key set → respects php.ini unlimited

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('-1');
});

// --- K, M, G suffixes are all accepted ---

it('accepts K suffix in memory limit', function () {
    // Use a large K value so PHP does not reject ini_set due to current memory usage.
    $config = new Config();
    $config->set('memoryLimit', '1048576K'); // 1G in kilobytes

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('1048576K');
});

it('accepts M suffix in memory limit', function () {
    $config = new Config();
    $config->set('memoryLimit', '256M');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('256M');
});

it('accepts G suffix in memory limit', function () {
    $config = new Config();
    $config->set('memoryLimit', '4G');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('4G');
});

it('accepts a plain numeric memory limit without suffix', function () {
    $config = new Config();
    $config->set('memoryLimit', '1073741824');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('1073741824');
});

// --- Suffix matching is case-insensitive ---

it('accepts lowercase "g" suffix the same as uppercase "G"', function () {
    $config = new Config();
    $config->set('memoryLimit', '2g');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('2g');
});

it('accepts lowercase "m" suffix the same as uppercase "M"', function () {
    $config = new Config();
    $config->set('memoryLimit', '512m');

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('512m');
});

it('accepts lowercase "k" suffix the same as uppercase "K"', function () {
    // Use a large k value so PHP does not reject ini_set due to current memory usage.
    $config = new Config();
    $config->set('memoryLimit', '1048576k'); // 1G in kilobytes

    $config->applyMemoryLimit();

    expect(ini_get('memory_limit'))->toBe('1048576k');
});
