<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class CliOutput
{
    private ?CliFormatter $formatter = null;

    public function setFormatter(CliFormatter $formatter): void
    {
        $this->formatter = $formatter;
    }

    public function getFormatter(): ?CliFormatter
    {
        return $this->formatter;
    }

    public function out(string $message): static
    {
        file_put_contents('php://stdout', $message);

        return $this;
    }

    public function outNl(string $message = ''): static
    {
        $this->out(PHP_EOL.$message);

        return $this;
    }

    public function cls(): static
    {
        $this->out("\x0D");
        $this->out("\x1B[2K");

        return $this;
    }

    public function prompt(string $question, string $default = ''): string
    {
        $defaultHint = '' !== $default ? " [$default]" : '';
        $this->out($question.$defaultHint.': ');

        $input = trim((string) fgets(STDIN));

        return '' !== $input ? $input : $default;
    }

    public function outWithMemory(string $string): static
    {
        $memory = (string) memory_get_usage();

        $this->out($string.' '.$this->humanReadableBytes($memory, 2).' of memory');

        return $this;
    }

    private function humanReadableBytes(string $bytes, int $decimals = 0): string
    {
        $size = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor((strlen($bytes) - 1) / 3);

        $unit = $factor > 0 ? $size[$factor] : $size[0];
        $divisor = 1024 ** $factor;
        $value = $divisor > 0 ? ((int) $bytes) / $divisor : 0;

        return sprintf("%.{$decimals}f", $value).' '.$unit;
    }
}
