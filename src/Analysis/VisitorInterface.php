<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

interface VisitorInterface
{
    public function setPath(string $path): void;
}
