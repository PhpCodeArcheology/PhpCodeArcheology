<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

interface PathAwareVisitorInterface
{
    public function afterSetPath(string $path): void;
}
