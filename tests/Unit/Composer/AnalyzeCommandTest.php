<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Package\RootPackage;
use PhpCodeArch\Composer\AnalyzeCommand;
use Symfony\Component\Console\Input\ArrayInput;

function createCommand(?Composer $composer = null): AnalyzeCommand
{
    $command = new AnalyzeCommand();

    if (null !== $composer) {
        $command->setComposer($composer);
    }

    return $command;
}

function createInput(array $parameters = []): ArrayInput
{
    $command = createCommand();

    return new ArrayInput($parameters, $command->getDefinition());
}

function createComposerWithAutoload(array $autoload): Composer
{
    $package = new RootPackage('test/test', '1.0.0.0', '1.0.0');
    $package->setAutoload($autoload);

    $composer = Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($package);

    return $composer;
}

it('is named analyze', function () {
    $command = createCommand();

    expect($command->getName())->toBe('codearch:analyze');
});

it('has a description', function () {
    $command = createCommand();

    expect($command->getDescription())->toBe('Run PhpCodeArcheology static analysis');
});

it('builds empty argv when no options given', function () {
    $input = createInput();

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe([]);
});

it('builds argv with --quick flag', function () {
    $input = createInput(['--quick' => true]);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--quick']);
});

it('builds argv with --report-type option', function () {
    $input = createInput(['--report-type' => 'json']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--report-type=json']);
});

it('builds argv with --report-dir option', function () {
    $input = createInput(['--report-dir' => '/tmp/report']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--report-dir=/tmp/report']);
});

it('builds argv with --coverage-file option', function () {
    $input = createInput(['--coverage-file' => 'clover.xml']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--coverage-file=clover.xml']);
});

it('builds argv with --fail-on option', function () {
    $input = createInput(['--fail-on' => 'error']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--fail-on=error']);
});

it('builds argv with --extensions option', function () {
    $input = createInput(['--extensions' => 'php,inc']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--extensions=php,inc']);
});

it('builds argv with --exclude option', function () {
    $input = createInput(['--exclude' => 'vendor,tests']);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--exclude=vendor,tests']);
});

it('builds argv with explicit paths', function () {
    $input = createInput(['path' => ['src/', 'lib/']]);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['src/', 'lib/']);
});

it('builds argv with paths and options combined', function () {
    $input = createInput([
        'path' => ['src/'],
        '--quick' => true,
        '--report-type' => 'json',
    ]);

    $argv = createCommand()->buildArgv($input);

    expect($argv)->toBe(['--quick', '--report-type=json', 'src/']);
});

it('detects PSR-4 source directories from autoload', function () {
    $composer = createComposerWithAutoload([
        'psr-4' => [
            'App\\' => 'src/',
            'Lib\\' => 'lib/',
        ],
    ]);

    $command = createCommand($composer);
    $dirs = $command->detectSourceDirs();

    expect($dirs)->toBe(['src', 'lib']);
});

it('handles multiple paths per namespace in autoload', function () {
    $composer = createComposerWithAutoload([
        'psr-4' => [
            'App\\' => ['src/', 'app/'],
        ],
    ]);

    $command = createCommand($composer);
    $dirs = $command->detectSourceDirs();

    expect($dirs)->toBe(['src', 'app']);
});

it('deduplicates autoload paths', function () {
    $composer = createComposerWithAutoload([
        'psr-4' => [
            'App\\' => 'src/',
            'Other\\' => 'src/',
        ],
    ]);

    $command = createCommand($composer);
    $dirs = $command->detectSourceDirs();

    expect($dirs)->toBe(['src']);
});

it('skips empty autoload paths', function () {
    $composer = createComposerWithAutoload([
        'psr-4' => [
            'App\\' => '',
        ],
    ]);

    $command = createCommand($composer);
    $dirs = $command->detectSourceDirs();

    expect($dirs)->toBe([]);
});

it('returns empty array when no PSR-4 autoload exists', function () {
    $composer = createComposerWithAutoload([]);

    $command = createCommand($composer);
    $dirs = $command->detectSourceDirs();

    expect($dirs)->toBe([]);
});
