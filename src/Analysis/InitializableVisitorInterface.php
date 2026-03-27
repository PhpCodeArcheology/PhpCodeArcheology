<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

interface InitializableVisitorInterface
{
    public function init(): void;
}
