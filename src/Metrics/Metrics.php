<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;

class Metrics
{
    use MetricsTrait;

    public function push(MetricsInterface $metrics): void
    {
        $this->metrics[(string) $metrics->getIdentifier()] = $metrics;
    }
}