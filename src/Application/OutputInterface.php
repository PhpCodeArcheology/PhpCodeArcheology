<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

interface OutputInterface
{
    public function out(string $text): void;

    public function outNl(string $text = ''): void;

    public function prompt(string $question, string $default = ''): string;
}
