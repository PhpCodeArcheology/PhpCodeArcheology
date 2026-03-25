<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\FrameworkDetectionResult;

it('detects test frameworks (hasTestFramework)', function () {
    $result = new FrameworkDetectionResult(pestDetected: true);

    expect($result->hasTestFramework())->toBeTrue();
});

it('returns false when no test framework detected', function () {
    $result = new FrameworkDetectionResult();

    expect($result->hasTestFramework())->toBeFalse();
});

it('returns correct test framework names', function () {
    $result = new FrameworkDetectionResult(phpunitDetected: true, pestDetected: true);

    expect($result->getTestFrameworkNames())->toBe(['PHPUnit', 'Pest']);
});

it('returns test framework summary string', function () {
    $result = new FrameworkDetectionResult(codeceptionDetected: true);

    expect($result->getTestFrameworkSummary())->toBe('Codeception');
});

it('returns empty summary when no test framework', function () {
    $result = new FrameworkDetectionResult();

    expect($result->getTestFrameworkSummary())->toBe('');
});

it('stores PSR-4 autoload data', function () {
    $psr4    = ['App\\' => 'src/'];
    $psr4Dev = ['Tests\\' => 'tests/'];

    $result = new FrameworkDetectionResult(
        psr4Autoload:    $psr4,
        psr4AutoloadDev: $psr4Dev,
    );

    expect($result->psr4Autoload)->toBe($psr4)
        ->and($result->psr4AutoloadDev)->toBe($psr4Dev);
});
