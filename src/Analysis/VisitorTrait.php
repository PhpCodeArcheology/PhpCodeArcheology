<?php

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Repository\RepositoryInterface;

trait VisitorTrait
{
    private string $path;

    public function __construct(
        /**
         * @var RepositoryInterface $repository
         */
        private readonly RepositoryInterface $repository,
    )
    {
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    public function setPath(string $path): void
    {
        $this->path = $path;

        if (method_exists($this, 'afterSetPath')) {
            $this->afterSetPath();
        }
    }
}
