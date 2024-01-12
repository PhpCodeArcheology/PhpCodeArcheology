<?php

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Manager\MetricType;
use PhpCodeArch\Metrics\Manager\MetricValue;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;

trait VisitorTrait
{
    private string $path;

    private FileMetrics $fileMetrics;

    private ProjectMetrics $projectMetrics;

    public function __construct(
        /**
         * @var Metrics $metrics
         */
        private readonly Metrics $metrics,

        /**
         * @var MetricType[] $usedMetricTypes
         */
        private readonly array $usedMetricTypes
    )
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

    private function setMetricValue(MetricsInterface &$metrics, string $key, mixed $value): void
    {
        $metrics->set($key, MetricValue::ofValueAndType($value, $this->usedMetricTypes[$key]));
    }

    private function setMetricValues(MetricsInterface &$metrics, array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValue($metrics, $key, $value);
        }
    }
}
