<?php

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;

interface MetricsCollectionInterface
{
    public function getIdentifier(): IdentifierInterface;
}
