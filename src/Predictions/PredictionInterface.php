<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

interface PredictionInterface
{
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    public function predict(): int;

    public function getLevel(): int;
}
