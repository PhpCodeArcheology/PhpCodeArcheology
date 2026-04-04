<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use PhpCodeArch\Composer\AnalyzePlugin;

it('implements PluginInterface and Capable', function () {
    $plugin = new AnalyzePlugin();

    expect($plugin)->toBeInstanceOf(\Composer\Plugin\PluginInterface::class)
        ->and($plugin)->toBeInstanceOf(\Composer\Plugin\Capable::class);
});

it('returns CommandProvider capability', function () {
    $plugin = new AnalyzePlugin();

    $capabilities = $plugin->getCapabilities();

    expect($capabilities)->toHaveKey(CommandProviderCapability::class)
        ->and($capabilities[CommandProviderCapability::class])->toBe(PhpCodeArch\Composer\CommandProvider::class);
});

it('activate/deactivate/uninstall are no-ops', function () {
    $plugin = new AnalyzePlugin();
    $composer = Mockery::mock(Composer::class);
    $io = new NullIO();

    $plugin->activate($composer, $io);
    $plugin->deactivate($composer, $io);
    $plugin->uninstall($composer, $io);

    expect(true)->toBeTrue();
});
