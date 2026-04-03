<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Output;

use PhpCodeArch\Application\CliOutput;

class StderrOutput extends CliOutput
{
    public function out(string $message): void
    {
        fwrite(STDERR, $message);
    }

    public function cls(): static
    {
        return $this; // Kein ANSI clear auf stderr
    }
}
