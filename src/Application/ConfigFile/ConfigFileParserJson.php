<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application\ConfigFile;

use Marcus\PhpLegacyAnalyzer\Application\Config;

class ConfigFileParserJson implements ConfigFileParserInterface
{
    public function __construct(private string $file)
    {
    }

    public function parse(Config $config): void
    {

    }

    public function parseJson(string $json, Config $config): void
    {
        $data = json_decode($json, true);

        if (isset($data['include'])) {
            $config->set('files', $data['include']);
        }

        if (isset($data['exclude'])) {
            $config->set('exclude', $data['exclude']);
        }

        if (isset($data['extensions'])) {
            $config->set('extensions', $data['extensions']);
        }
    }
}
