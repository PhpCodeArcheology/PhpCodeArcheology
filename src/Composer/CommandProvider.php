<?php

declare(strict_types=1);

namespace PhpCodeArch\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    /** @return list<AnalyzeCommand> */
    public function getCommands(): array
    {
        return [new AnalyzeCommand()];
    }
}
