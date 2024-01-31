<?php

declare(strict_types=1);

namespace PhpCodeArch\Predictions;

use PhpCodeArch\Repository\RepositoryInterface;

interface PredictionInterface
{
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;

    public function predict(RepositoryInterface $repository): int;

    public function getLevel(): int;
}
