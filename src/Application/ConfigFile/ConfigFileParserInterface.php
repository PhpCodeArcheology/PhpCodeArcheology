<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application\ConfigFile;

use Marcus\PhpLegacyAnalyzer\Application\Config;

interface ConfigFileParserInterface
{
    public function parse(Config $config): void;
}
