<?php

declare(strict_types=1);

use PhpCodeArch\Application\RequirementsChecker;

beforeEach(function () {
    $this->checker = new RequirementsChecker();
});

it('reports no missing extensions when all are loaded', function () {
    // The test suite itself needs mbstring and simplexml, so this must be empty here.
    expect($this->checker->getMissingExtensions())->toBe([]);
});

it('names the missing extension and its package in the error message', function () {
    $message = $this->checker->getErrorMessage(['simplexml']);

    expect($message)->toContain('simplexml')
        ->and($message)->toContain('SimpleXML')
        ->and($message)->toContain('-xml')
        ->and($message)->toStartWith('Error: PhpCodeArcheology requires PHP extensions');
});

it('lists every missing extension', function () {
    $message = $this->checker->getErrorMessage(['mbstring', 'simplexml']);

    expect($message)->toContain('mbstring')
        ->and($message)->toContain('Multibyte String')
        ->and($message)->toContain('simplexml');
});

it('falls back to the bare name for unknown extensions', function () {
    $message = $this->checker->getErrorMessage(['intl']);

    expect($message)->toContain('intl');
});
