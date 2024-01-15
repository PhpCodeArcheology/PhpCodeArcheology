<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\ProjectMetrics;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Identity\ProjectIdentifier;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionTrait;

class ProjectMetricsCollection implements MetricsCollectionInterface
{
    use MetricsCollectionTrait;

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
