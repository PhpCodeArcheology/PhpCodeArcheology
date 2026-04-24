<?php

declare(strict_types=1);

use PhpCodeArch\Calculators\CodeDuplicationCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;

// Helpers ─────────────────────────────────────────────────────────────────────

/**
 * Create a temp PHP file whose content generates at least MIN_TOKENS (50)
 * meaningful tokens so the calculator produces hashes for it.
 */
function makeDupTmpFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'dup_test_');
    file_put_contents($path, $content);

    return $path;
}

/**
 * Build a snippet of PHP that reliably reaches ≥50 non-whitespace tokens.
 * We use a simple class with several methods so the token count is stable.
 */
function longPhpSnippet(string $suffix = ''): string
{
    return <<<PHP
    <?php
    class Dummy{$suffix}
    {
        public function alpha(\$a, \$b) { return \$a + \$b; }
        public function beta(\$x)  { if (\$x > 0) { return \$x * 2; } return 0; }
        public function gamma(\$n) { \$r = 0; for (\$i = 0; \$i < \$n; \$i++) { \$r += \$i; } return \$r; }
        public function delta(\$s) { return strtolower(\$s); }
        public function epsilon(\$a, \$b, \$c) { return \$a * \$b + \$c; }
    }
    PHP;
}

/**
 * Build a MetricsController wired up for file-level tests, register the given
 * real temp file in a FileCollection, and set its 'loc' metric.
 */
function setupControllerWithFile(string $filePath, int $loc = 50): array
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    $controller->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $filePath]
    );

    $controller->setMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $filePath],
        ['filePath' => $filePath, 'loc' => $loc]
    );

    $fileId = (string) $controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $filePath]
    )->getIdentifier();

    return [$controller, $fileId];
}

// ── beforeEach / afterEach: track temp files so they are cleaned up ───────────

beforeEach(function () {
    $this->tmpFiles = [];
});

afterEach(function () {
    foreach ($this->tmpFiles as $f) {
        if (file_exists($f)) {
            @unlink($f);
        }
    }
});

// ── Zero duplicates for unique code ──────────────────────────────────────────

it('returns zero duplicates when a file is unique (only one file)', function () {
    // With a single file there can be no cross-file duplication
    $file1 = makeDupTmpFile(longPhpSnippet('A'));
    $this->tmpFiles = [$file1];

    [$ctrl1, $id1] = setupControllerWithFile($file1, 60);

    $calc = new CodeDuplicationCalculator($ctrl1, $ctrl1, $ctrl1);
    $calc->beforeTraverse();

    foreach ($ctrl1->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $rate1 = $ctrl1->getMetricValueByIdentifierString($id1, 'duplicationRate')?->getValue() ?? 0;
    $overallRate = $ctrl1->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicationRate'
    )?->getValue() ?? 0;

    // Single file — no cross-file matches possible → 0 % duplication
    expect($rate1)->toBe(0.0)
        ->and($overallRate)->toBe(0.0);
});

// ── Detects duplicate code blocks ────────────────────────────────────────────

it('detects duplicate code blocks across two identical files', function () {
    // Both files contain exactly the same code → all token windows duplicate
    $sharedCode = longPhpSnippet('Shared');

    $file1 = makeDupTmpFile($sharedCode);
    $file2 = makeDupTmpFile($sharedCode);
    $this->tmpFiles = [$file1, $file2];

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    foreach ([$file1, $file2] as $f) {
        $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $f]);
        $controller->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $f],
            ['filePath' => $f, 'loc' => 60]
        );
    }

    $calc = new CodeDuplicationCalculator($controller, $controller, $controller);
    $calc->beforeTraverse();

    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $overallDup = $controller->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicatedLines'
    )?->getValue() ?? 0;

    $overallRate = $controller->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicationRate'
    )?->getValue() ?? 0;

    expect($overallDup)->toBeGreaterThan(0)
        ->and($overallRate)->toBeGreaterThan(0.0);
});

// ── Calculates duplication percentage ────────────────────────────────────────

it('calculates per-file duplication rate from duplicated lines and loc', function () {
    $sharedCode = longPhpSnippet('Dup');

    $file1 = makeDupTmpFile($sharedCode);
    $file2 = makeDupTmpFile($sharedCode);
    $this->tmpFiles = [$file1, $file2];

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    $loc = 80;
    foreach ([$file1, $file2] as $f) {
        $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $f]);
        $controller->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $f],
            ['filePath' => $f, 'loc' => $loc]
        );
    }

    $id1 = (string) $controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $file1]
    )->getIdentifier();

    $calc = new CodeDuplicationCalculator($controller, $controller, $controller);
    $calc->beforeTraverse();

    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $dupLines = $controller->getMetricValueByIdentifierString($id1, 'duplicatedLines')?->getValue() ?? 0;
    $rate = $controller->getMetricValueByIdentifierString($id1, 'duplicationRate')?->getValue() ?? 0.0;

    // rate = round((duplicatedLines / loc) * 100, 2)
    if ($dupLines > 0 && $loc > 0) {
        $expected = round(($dupLines / $loc) * 100, 2);
        expect($rate)->toBe($expected);
    } else {
        // If somehow no duplication was detected, rate must still be 0
        expect($rate)->toBe(0.0);
    }
});

// ── Small file (below MIN_TOKENS) gets zero duplication ───────────────────────

it('sets zero duplication for files below the minimum token threshold', function () {
    // Tiny snippet — well below 50 non-whitespace tokens
    $tiny = "<?php\necho 'hello';\n";

    $file1 = makeDupTmpFile($tiny);
    $file2 = makeDupTmpFile($tiny);
    $this->tmpFiles = [$file1, $file2];

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    foreach ([$file1, $file2] as $f) {
        $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $f]);
        $controller->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $f],
            ['filePath' => $f, 'loc' => 2]
        );
    }

    $id1 = (string) $controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $file1]
    )->getIdentifier();

    $calc = new CodeDuplicationCalculator($controller, $controller, $controller);
    $calc->beforeTraverse();

    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $rate = $controller->getMetricValueByIdentifierString($id1, 'duplicationRate')?->getValue() ?? 0.0;
    expect($rate)->toBe(0.0);
});

// ── Non-file collections are skipped ─────────────────────────────────────────

it('skips non-FileMetricsCollection entries without error', function () {
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);
    $controller->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => 'SomeClass', 'path' => '/src/SomeClass.php']
    );

    $calc = new CodeDuplicationCalculator($controller, $controller, $controller);
    $calc->beforeTraverse();

    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $overallRate = $controller->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicationRate'
    )?->getValue() ?? 0;

    expect($overallRate)->toBe(0.0);
});

// ── beforeTraverse resets state ───────────────────────────────────────────────

it('resets internal state on beforeTraverse so two runs are independent', function () {
    $sharedCode = longPhpSnippet('Reset');

    $file1 = makeDupTmpFile($sharedCode);
    $file2 = makeDupTmpFile($sharedCode);
    $this->tmpFiles = [$file1, $file2];

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    foreach ([$file1, $file2] as $f) {
        $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $f]);
        $controller->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $f],
            ['filePath' => $f, 'loc' => 60]
        );
    }

    $calc = new CodeDuplicationCalculator($controller, $controller, $controller);

    // First run
    $calc->beforeTraverse();
    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $after1 = $controller->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicatedLines'
    )?->getValue() ?? 0;

    // Second run — beforeTraverse must reset totalDuplicatedLines
    $calc->beforeTraverse();
    foreach ($controller->getAllCollections() as $col) {
        $calc->calculate($col);
    }
    $calc->afterTraverse();

    $after2 = $controller->getMetricValue(
        MetricCollectionTypeEnum::ProjectCollection, null, 'overallDuplicatedLines'
    )?->getValue() ?? 0;

    // Both runs with the same data should produce the same result
    expect($after1)->toBe($after2);
});
