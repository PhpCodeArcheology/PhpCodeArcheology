<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class CliFormatter
{
    private bool $colorEnabled;

    public function __construct(?bool $colorEnabled = null)
    {
        if ($colorEnabled !== null) {
            $this->colorEnabled = $colorEnabled;
        } else {
            $this->colorEnabled = $this->detectColorSupport();
        }
    }

    public function isColorEnabled(): bool
    {
        return $this->colorEnabled;
    }

    public function info(string $text): string
    {
        return $this->wrap($text, '34');
    }

    public function success(string $text): string
    {
        return $this->wrap($text, '32');
    }

    public function error(string $text): string
    {
        return $this->wrap($text, '31');
    }

    public function warning(string $text): string
    {
        return $this->wrap($text, '33');
    }

    public function bold(string $text): string
    {
        return $this->wrap($text, '1');
    }

    public function dim(string $text): string
    {
        return $this->wrap($text, '2');
    }

    private function wrap(string $text, string $code): string
    {
        if (!$this->colorEnabled) {
            return $text;
        }

        return "\033[{$code}m{$text}\033[0m";
    }

    private function detectColorSupport(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        return true;
    }
}
