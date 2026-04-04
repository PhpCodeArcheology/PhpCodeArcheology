<?php

declare(strict_types=1);

use PhpCodeArch\Composer\AnalyzeCommand;
use PhpCodeArch\Composer\CommandProvider;

it('provides the analyze command', function () {
    $provider = new CommandProvider();

    $commands = $provider->getCommands();

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toBeInstanceOf(AnalyzeCommand::class);
});
