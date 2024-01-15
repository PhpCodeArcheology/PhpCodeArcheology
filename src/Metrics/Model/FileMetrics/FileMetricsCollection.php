<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\FileMetrics;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionTrait;

class FileMetricsCollection implements MetricsCollectionInterface
{
    use MetricsCollectionTrait;

    private IdentifierInterface $identifier;

    public function __construct(
        private readonly string $path
    )
    {
        $this->identifier = FileIdentifier::ofPath($this->path);
    }

    public function getIdentifier(): IdentifierInterface
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->path;
    }
}
