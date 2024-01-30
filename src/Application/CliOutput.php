<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class CliOutput
{
    public function out(string $message): static
    {
        file_put_contents('php://stdout', $message);

        return $this;
    }

    public function outNl(string $message = ''): static
    {
        $this->out(PHP_EOL . $message);

        return $this;
    }

    public function cls(): static
    {
        $this->out("\x0D");
        $this->out("\x1B[2K");

        return $this;
    }

    public function outWithMemory(string $string): static
    {
        $memory = (string) memory_get_usage();

        $this->out($string . ' ' . $this->humanReadableBytes($memory, 2) . " of memory");

        return $this;
    }

    private function humanReadableBytes(string $bytes, int $decimals = 0): string
    {
        $size = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        if ($factor > 0) {
            $unit = $size[$factor];
        }
        else {
            $unit = $size[0];
        }

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $unit;
    }
}
