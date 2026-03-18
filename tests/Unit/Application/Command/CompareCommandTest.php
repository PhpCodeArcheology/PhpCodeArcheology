<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Command\CompareCommand;
use PhpCodeArch\Application\Config;

function createCompareCommandDeps(array $args): array
{
    $config = new Config();
    $config->set('commandArgs', $args);

    $output = Mockery::mock(CliOutput::class);
    $output->shouldReceive('outNl')->andReturnSelf();
    $output->shouldReceive('out')->andReturnSelf();

    $formatter = new CliFormatter(false);

    return [new CompareCommand(), $config, $output, $formatter];
}

function createTempJsonFile(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'pca_compare_test_');
    file_put_contents($path, json_encode($data));

    return $path;
}

function makeReport(array $metrics = [], array $problems = []): array
{
    $metricsFormatted = [];
    foreach ($metrics as $key => $value) {
        $metricsFormatted[$key] = ['value' => $value, 'name' => $key];
    }

    return [
        'version' => '1.0',
        'project' => ['metrics' => $metricsFormatted],
        'problems' => $problems,
    ];
}

it('returns error for wrong argument count', function () {
    [$command, $config, $output, $formatter] = createCompareCommandDeps(['only-one-file.json']);

    $result = $command->execute($config, $output, $formatter);

    expect($result)->toBe(1);
});

it('returns error for zero arguments', function () {
    [$command, $config, $output, $formatter] = createCompareCommandDeps([]);

    $result = $command->execute($config, $output, $formatter);

    expect($result)->toBe(1);
});

it('returns error for non-existent files', function () {
    [$command, $config, $output, $formatter] = createCompareCommandDeps([
        '/tmp/pca_nonexistent_before.json',
        '/tmp/pca_nonexistent_after.json',
    ]);

    $result = $command->execute($config, $output, $formatter);

    expect($result)->toBe(1);
});

it('returns error for invalid JSON', function () {
    $fileBefore = tempnam(sys_get_temp_dir(), 'pca_compare_test_');
    $fileAfter = tempnam(sys_get_temp_dir(), 'pca_compare_test_');
    file_put_contents($fileBefore, 'not valid json');
    file_put_contents($fileAfter, '{ broken');

    [$command, $config, $output, $formatter] = createCompareCommandDeps([$fileBefore, $fileAfter]);

    $result = $command->execute($config, $output, $formatter);

    expect($result)->toBe(1);

    @unlink($fileBefore);
    @unlink($fileAfter);
});

it('compares identical reports with zero deltas', function () {
    $report = makeReport(
        ['overallErrorCount' => 10, 'overallAvgCC' => 5.0],
        [['entityId' => 'Foo', 'message' => 'test msg', 'level' => 'error']]
    );

    $fileBefore = createTempJsonFile($report);
    $fileAfter = createTempJsonFile($report);

    [$command, $config, $output, $formatter] = createCompareCommandDeps([$fileBefore, $fileAfter]);

    $result = $command->execute($config, $output, $formatter);

    expect($result)->toBe(0);

    @unlink($fileBefore);
    @unlink($fileAfter);
});

it('detects new problems', function () {
    $before = makeReport(
        ['overallErrorCount' => 1],
        [['entityId' => 'Foo', 'message' => 'existing issue', 'level' => 'error']]
    );

    $after = makeReport(
        ['overallErrorCount' => 2],
        [
            ['entityId' => 'Foo', 'message' => 'existing issue', 'level' => 'error'],
            ['entityId' => 'Bar', 'message' => 'new issue', 'level' => 'warning'],
        ]
    );

    $fileBefore = createTempJsonFile($before);
    $fileAfter = createTempJsonFile($after);

    $capturedOutput = [];
    $output = Mockery::mock(CliOutput::class);
    $output->shouldReceive('outNl')->andReturnUsing(function (string $msg = '') use (&$capturedOutput, $output) {
        $capturedOutput[] = $msg;
        return $output;
    });
    $output->shouldReceive('out')->andReturnSelf();

    $config = new Config();
    $config->set('commandArgs', [$fileBefore, $fileAfter]);

    $formatter = new CliFormatter(false);
    $command = new CompareCommand();

    $result = $command->execute($config, $output, $formatter);

    $fullOutput = implode("\n", $capturedOutput);

    expect($result)->toBe(0)
        ->and($fullOutput)->toContain('New Problems')
        ->and($fullOutput)->toContain('new issue');

    @unlink($fileBefore);
    @unlink($fileAfter);
});

it('detects resolved problems', function () {
    $before = makeReport(
        ['overallErrorCount' => 2],
        [
            ['entityId' => 'Foo', 'message' => 'remaining issue', 'level' => 'error'],
            ['entityId' => 'Bar', 'message' => 'fixed issue', 'level' => 'warning'],
        ]
    );

    $after = makeReport(
        ['overallErrorCount' => 1],
        [['entityId' => 'Foo', 'message' => 'remaining issue', 'level' => 'error']]
    );

    $fileBefore = createTempJsonFile($before);
    $fileAfter = createTempJsonFile($after);

    $capturedOutput = [];
    $output = Mockery::mock(CliOutput::class);
    $output->shouldReceive('outNl')->andReturnUsing(function (string $msg = '') use (&$capturedOutput, $output) {
        $capturedOutput[] = $msg;
        return $output;
    });
    $output->shouldReceive('out')->andReturnSelf();

    $config = new Config();
    $config->set('commandArgs', [$fileBefore, $fileAfter]);

    $formatter = new CliFormatter(false);
    $command = new CompareCommand();

    $result = $command->execute($config, $output, $formatter);

    $fullOutput = implode("\n", $capturedOutput);

    expect($result)->toBe(0)
        ->and($fullOutput)->toContain('Resolved Problems')
        ->and($fullOutput)->toContain('fixed issue');

    @unlink($fileBefore);
    @unlink($fileAfter);
});
