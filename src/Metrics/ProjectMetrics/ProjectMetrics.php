<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\ProjectMetrics;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Identity\ProjectIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\MetricsTrait;

class ProjectMetrics implements MetricsInterface
{
    use MetricsTrait;

    private IdentifierInterface $identifier;

    public function __construct(private readonly string $path)
    {
        $this->identifier = ProjectIdentifier::ofPath($this->path);
    }

    public function getIdentifier(): IdentifierInterface
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return 'Project';
    }
}
