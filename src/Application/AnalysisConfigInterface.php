<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

interface AnalysisConfigInterface
{
    public function get(string $key): mixed;

    public function has(string $key): bool;

    public function getRunningDir(): string;

    /** @return array<mixed> */
    public function getFiles(): array;
}
