<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;

trait VisitorTrait
{
    private string $path;

    public function __construct(
        protected readonly MetricsWriterInterface $writer,
        protected readonly MetricsRegistryInterface $registry,
    ) {
        if ($this instanceof InitializableVisitorInterface) {
            $this->init();
        }
    }

    public function setPath(string $path): void
    {
        $this->path = $path;

        if ($this instanceof PathAwareVisitorInterface) {
            $this->afterSetPath($path);
        }
    }
}
