<?php

namespace PhpCodeArch\Metrics;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;

interface MetricsInterface
{
    public function getIdentifier(): IdentifierInterface;
}
