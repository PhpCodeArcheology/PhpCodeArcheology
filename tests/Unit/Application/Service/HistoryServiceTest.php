<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\HistoryService;
use PhpCodeArch\Metrics\Controller\MetricsController;

// Helpers ─────────────────────────────────────────────────────────────────────

function makeTmpDir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'history_test_' . uniqid();
    mkdir($dir, 0777, true);
    return $dir;
}

function makeConfig(string $dir): Config
{
    $config = new Config();
    $config->set('reportDir', $dir);
    return $config;
}

/**
 * A MetricsController that returns no collections — used when writeHistory
 * is under test and we don't want metric type resolution to run.
 */
function makeEmptyMockedController(): MetricsController
{
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getAllCollections')->andReturn([]);
    return $mc;
}

/**
 * Pre-write a JSONL history file with a single entry carrying the given date
 * and optional data payload.
 */
function writeFixtureJsonl(string $dir, string $date, array $data = []): void
{
    $entry = json_encode(['date' => $date, 'data' => $data]);
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'history.jsonl', $entry . "\n");
}

// ── beforeEach / afterEach ────────────────────────────────────────────────────

beforeEach(function () {
    $this->tmpDir  = makeTmpDir();
    $this->service = new HistoryService();
});

afterEach(function () {
    $files = glob($this->tmpDir . DIRECTORY_SEPARATOR . '*');
    if ($files) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
    @rmdir($this->tmpDir);
});

// ── writeHistory: creates file ────────────────────────────────────────────────

it('writes a history entry to a jsonl file', function () {
    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    // Pre-create empty file to avoid PHPUnit capturing the @file() warning
    // that getLastLineOfFile emits when the file doesn't exist yet.
    touch($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl');

    $this->service->writeHistory($mc, $config);

    $historyFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl';
    expect(file_exists($historyFile))->toBeTrue();

    $lines = @file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    expect($lines)->toHaveCount(1);

    $entry = json_decode($lines[0], true);
    expect($entry)->toHaveKey('date')
        ->and($entry)->toHaveKey('data');
});

it('date in history entry matches Y-m-d-H-i-s format', function () {
    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    touch($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl');
    $this->service->writeHistory($mc, $config);

    $historyFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl';
    $lines = @file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $entry = json_decode($lines[0], true);

    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', $entry['date']);
    expect($parsed)->toBeInstanceOf(\DateTimeImmutable::class);
});

// ── writeHistory: deduplication behavior ─────────────────────────────────────

it('updates the timestamp of the last entry when data is unchanged', function () {
    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    touch($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl');
    $this->service->writeHistory($mc, $config);
    $this->service->writeHistory($mc, $config);

    $historyFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl';
    $lines = @file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    // Same data → only one entry
    expect(count($lines))->toBe(1);
});

it('appends a new entry when the history file does not exist yet', function () {
    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    touch($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl');
    $this->service->writeHistory($mc, $config);

    $historyFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl';
    expect(file_exists($historyFile))->toBeTrue();
});

// ── writeHistory: legacy JSON migration ──────────────────────────────────────

it('migrates legacy history.json to history.jsonl on write', function () {
    $oldFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.json';
    $date    = (new \DateTimeImmutable())->format('Y-m-d-H-i-s');
    file_put_contents($oldFile, json_encode(['date' => $date, 'data' => []]));

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    $this->service->writeHistory($mc, $config);

    $newFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl';
    expect(file_exists($newFile))->toBeTrue()
        ->and(file_exists($oldFile))->toBeFalse();
});

// ── setDeltas: missing file ───────────────────────────────────────────────────

it('returns false when no history file exists', function () {
    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    $result = $this->service->setDeltas($mc, $config);

    expect($result)->toBeFalse();
});

// ── setDeltas: reads from valid jsonl ─────────────────────────────────────────

it('returns a DateTimeImmutable when a valid jsonl history file exists', function () {
    $date = (new \DateTimeImmutable())->format('Y-m-d-H-i-s');
    writeFixtureJsonl($this->tmpDir, $date);

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    $result = $this->service->setDeltas($mc, $config);

    expect($result)->toBeInstanceOf(\DateTimeImmutable::class);
});

it('returns the correct date from the jsonl file', function () {
    $date = '2025-06-15-10-30-00';
    writeFixtureJsonl($this->tmpDir, $date);

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    $result = $this->service->setDeltas($mc, $config);

    expect($result->format('Y-m-d-H-i-s'))->toBe($date);
});

// ── setDeltas: reads from legacy JSON ────────────────────────────────────────

it('reads deltas from a legacy history.json file', function () {
    $date = (new \DateTimeImmutable())->format('Y-m-d-H-i-s');

    // Write old-style single-object JSON (not JSONL)
    file_put_contents(
        $this->tmpDir . DIRECTORY_SEPARATOR . 'history.json',
        json_encode(['date' => $date, 'data' => []])
    );

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    $result = $this->service->setDeltas($mc, $config);

    expect($result)->toBeInstanceOf(\DateTimeImmutable::class);
});

// ── setDeltas: edge cases ────────────────────────────────────────────────────

it('returns false when history.jsonl is empty', function () {
    file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl', '');

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    expect($this->service->setDeltas($mc, $config))->toBeFalse();
});

it('returns false when history.jsonl contains invalid JSON', function () {
    file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl', "not-valid-json\n");

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    expect($this->service->setDeltas($mc, $config))->toBeFalse();
});

it('returns false when history.jsonl entry is missing the date field', function () {
    file_put_contents(
        $this->tmpDir . DIRECTORY_SEPARATOR . 'history.jsonl',
        json_encode(['data' => []]) . "\n"
    );

    $mc     = makeEmptyMockedController();
    $config = makeConfig($this->tmpDir);

    expect($this->service->setDeltas($mc, $config))->toBeFalse();
});
