<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

final class ArgumentParser
{
    public function parse(array $argv): Config
    {
        $config = new Config();

        if (count($argv) === 0) {
            return $config;
        }

        if (str_ends_with($argv[0], 'php-legacy-analyzer')) {
            array_shift($argv);
        }

        $config->set('files', $argv);

        return $config;
    }
}