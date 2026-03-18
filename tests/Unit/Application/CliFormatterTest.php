<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliFormatter;

it('returns colored text when enabled', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->info('hello');

    expect($result)->toContain("\033[");
});

it('returns plain text when disabled', function () {
    $formatter = new CliFormatter(false);

    $result = $formatter->info('hello');

    expect($result)->toBe('hello')
        ->and($result)->not->toContain("\033[");
});

it('wraps info() with blue code 34', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->info('message');

    expect($result)->toBe("\033[34mmessage\033[0m");
});

it('wraps success() with green code 32', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->success('message');

    expect($result)->toBe("\033[32mmessage\033[0m");
});

it('wraps error() with red code 31', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->error('message');

    expect($result)->toBe("\033[31mmessage\033[0m");
});

it('wraps warning() with yellow code 33', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->warning('message');

    expect($result)->toBe("\033[33mmessage\033[0m");
});

it('wraps bold() with code 1', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->bold('message');

    expect($result)->toBe("\033[1mmessage\033[0m");
});

it('wraps dim() with code 2', function () {
    $formatter = new CliFormatter(true);

    $result = $formatter->dim('message');

    expect($result)->toBe("\033[2mmessage\033[0m");
});

it('auto-detects NO_COLOR env variable', function () {
    putenv('NO_COLOR=1');

    $formatter = new CliFormatter();

    expect($formatter->isColorEnabled())->toBeFalse();

    // Clean up
    putenv('NO_COLOR');
});
