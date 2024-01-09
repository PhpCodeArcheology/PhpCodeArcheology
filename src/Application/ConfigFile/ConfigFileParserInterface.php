<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\ConfigFile;

use PhpCodeArch\Application\Config;

interface ConfigFileParserInterface
{
    public function parse(Config $config): void;
}
