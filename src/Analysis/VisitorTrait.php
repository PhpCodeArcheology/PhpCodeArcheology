<?php

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;

trait VisitorTrait
{
    private string $path;

    private FileMetrics $fileMetrics;

    private ProjectMetrics $projectMetrics;

    public function __construct(private readonly Metrics $metrics)
    {
    }

    public function setPath(string $path): void
    {
        $this->path = $path;

        if (method_exists($this, 'afterSetPath')) {
            $this->afterSetPath();
        }
    }

    private function getFileMetrics(): void
    {
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $this->fileMetrics = $this->metrics->get($fileId);
    }
}
