<?php

declare(strict_types=1);

use PhpCodeArch\Application\Application;
use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Command\BaselineCommand;
use PhpCodeArch\Application\Config;

function makeBaselineCommand(): BaselineCommand
{
    return new BaselineCommand(new Application());
}

function baselineOutput(): CliOutput
{
    $output = Mockery::mock(CliOutput::class)->shouldIgnoreMissing();
    return $output;
}

// Temp dir with a minimal PHP file for integration tests
function makeBaselineTempDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/pca-baseline-test-' . uniqid();
    mkdir($tmpDir . '/src', 0755, true);
    file_put_contents($tmpDir . '/src/Foo.php', "<?php\n\nclass Foo {\n    public function bar(): void {}\n}\n");
    return $tmpDir;
}

function cleanupDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

it('returns error when subcommand argument is missing', function () {
    $command   = makeBaselineCommand();
    $config    = new Config();
    $config->set('commandArgs', []);
    $formatter = new CliFormatter(false);

    $result = $command->execute($config, baselineOutput(), $formatter);

    expect($result)->toBe(1);
});

it('returns error for unknown subcommand', function () {
    $command   = makeBaselineCommand();
    $config    = new Config();
    $config->set('commandArgs', ['invalid']);
    $formatter = new CliFormatter(false);

    $result = $command->execute($config, baselineOutput(), $formatter);

    expect($result)->toBe(1);
});

it('creates a baseline JSON file', function () {
    $tmpDir  = makeBaselineTempDir();
    $command = makeBaselineCommand();

    $config = new Config();
    $config->set('commandArgs', ['create', $tmpDir . '/src']);
    $config->set('reportDir', $tmpDir);

    $result = $command->execute($config, baselineOutput(), new CliFormatter(false));

    expect($result)->toBe(0);
    expect(file_exists($tmpDir . '/.phpcodearch-baseline.json'))->toBeTrue();

    cleanupDir($tmpDir);
});

it('baseline JSON file contains required keys', function () {
    $tmpDir  = makeBaselineTempDir();
    $command = makeBaselineCommand();

    $config = new Config();
    $config->set('commandArgs', ['create', $tmpDir . '/src']);
    $config->set('reportDir', $tmpDir);

    $command->execute($config, baselineOutput(), new CliFormatter(false));

    $data = json_decode(file_get_contents($tmpDir . '/.phpcodearch-baseline.json'), true);

    expect($data)->toHaveKeys(['createdAt', 'toolVersion', 'problemCounts', 'problems']);
    expect($data['problems'])->toBeArray();

    cleanupDir($tmpDir);
});

it('overwrites an existing baseline file', function () {
    $tmpDir  = makeBaselineTempDir();
    $command = makeBaselineCommand();

    $config = new Config();
    $config->set('commandArgs', ['create', $tmpDir . '/src']);
    $config->set('reportDir', $tmpDir);

    // First run
    $command->execute($config, baselineOutput(), new CliFormatter(false));
    $firstMtime = filemtime($tmpDir . '/.phpcodearch-baseline.json');

    // Second run (overwrite) — sleep 1s to ensure mtime differs
    sleep(1);
    $config2 = new Config();
    $config2->set('commandArgs', ['create', $tmpDir . '/src']);
    $config2->set('reportDir', $tmpDir);

    $result = $command->execute($config2, baselineOutput(), new CliFormatter(false));
    $secondMtime = filemtime($tmpDir . '/.phpcodearch-baseline.json');

    expect($result)->toBe(0);
    expect($secondMtime)->toBeGreaterThan($firstMtime);

    cleanupDir($tmpDir);
});
