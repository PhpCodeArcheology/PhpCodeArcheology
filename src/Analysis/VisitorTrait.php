<?php

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
