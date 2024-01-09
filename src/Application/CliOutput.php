<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class CliOutput
{
    public function out(string $message): CliOutput
    {
        file_put_contents('php://stdout', $message);

        return $this;
    }

    public function outNl(string $message = ''): CliOutput
    {
        $this->out(PHP_EOL . $message);

        return $this;
    }

    public function cls(): CliOutput
    {
        $this->out("\x0D");
        $this->out("\x1B[2K");

        return $this;
    }
}
