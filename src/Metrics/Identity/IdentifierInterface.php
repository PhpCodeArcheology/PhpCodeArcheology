<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Identity;

interface IdentifierInterface
{
    public function __toString(): string;
}
