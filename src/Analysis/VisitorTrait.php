<?php

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;

trait VisitorTrait
{
    private string $path;

    private FileMetrics $fileMetrics;

    private ProjectMetrics $projectMetrics;

    public function __construct(private Metrics $metrics)
    {
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    private function getFileMetrics(): void
    {
        $fileId = (string) FileIdentifier::ofPath($this->path);
        $this->fileMetrics = $this->metrics->get($fileId);
    }
}