<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\Controller\MetricsController;

trait VisitorTrait
{
    private string $path;

    public function __construct(
        /**
         * @var MetricsController $metricsController
         */
        private readonly MetricsController $metricsController,
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
