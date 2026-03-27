<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Application\Config;

interface ConfigAwareVisitorInterface
{
    public function injectConfig(Config $config): void;
}
