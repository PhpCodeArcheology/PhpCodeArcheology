<?php

declare(strict_types=1);

namespace PhpCodeArch\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

final class AnalyzePlugin implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /** @return array<class-string, class-string> */
    public function getCapabilities(): array
    {
        return [
            \Composer\Plugin\Capability\CommandProvider::class => CommandProvider::class,
        ];
    }
}
