<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\DocumentationCoverageVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__.'/test_helpers.php';

function getDocBlockVisitors(): array
{
    return [
        IdentifyVisitor::class,
        DocumentationCoverageVisitor::class,
    ];
}

it('extracts class docblock summary', function () {
    $testFile = __DIR__.'/testfiles/docblock-summary.php';
    $metricsController = getMetricsForVisitors($testFile, getDocBlockVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        $name = $metrics->getString('singleName');
        $summary = $metrics->get('docBlockSummary')->getValue();

        if ('ClassWithDocBlock' === $name) {
            // Hand-calculated: summary text before @package tag
            expect($summary)->toBe("A service that handles user authentication.\n\nThis is the main entry point for login flows.");
        }

        if ('ClassWithoutDocBlock' === $name) {
            expect($summary)->toBe('');
        }
    }
});

it('extracts method docblock summary for all visibility levels', function () {
    $testFile = __DIR__.'/testfiles/docblock-summary.php';
    $metricsController = getMetricsForVisitors($testFile, getDocBlockVisitors());

    $methodSummaries = [];

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('method' !== $metrics->get('functionType')?->getValue()) {
            continue;
        }

        $name = $metrics->getName();
        $summary = $metrics->get('docBlockSummary')->getValue();
        $methodSummaries[$name] = $summary;
    }

    // Public method with summary + tags
    expect($methodSummaries['authenticate'])->toBe("Authenticate the given user.\n\nValidates credentials against the database.");

    // Public method with only tags, no summary text
    expect($methodSummaries['validateToken'])->toBe('');

    // Public method with email in summary (must NOT cut off at @)
    expect($methodSummaries['getHelpText'])->toBe('Contact admin@example.com for access issues.');

    // Private method -- must also get a summary
    expect($methodSummaries['isSessionValid'])->toBe('Check if the session is still valid.');

    // Magic method __construct -- must also get a summary
    expect($methodSummaries['__construct'])->toBe('Create a new instance with default settings.');
});

it('extracts function docblock summary', function () {
    $testFile = __DIR__.'/testfiles/docblock-summary.php';
    $metricsController = getMetricsForVisitors($testFile, getDocBlockVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('function' !== $metrics->get('functionType')?->getValue()) {
            continue;
        }

        $name = $metrics->getString('singleName');
        $summary = $metrics->get('docBlockSummary')->getValue();

        if ('helperWithDocBlock' === $name) {
            expect($summary)->toBe("A standalone helper function.\n\nDoes something useful.");
        }

        if ('helperWithoutDocBlock' === $name) {
            expect($summary)->toBe('');
        }
    }
});
