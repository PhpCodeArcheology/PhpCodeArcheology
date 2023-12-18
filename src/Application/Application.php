<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

final class Application
{
    public function run(array $argv): void
    {
        (new ArgumentParser())->parse($argv);
    }
}
