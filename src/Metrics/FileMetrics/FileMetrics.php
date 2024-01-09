<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\FileMetrics;


use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\MetricsTrait;

class FileMetrics implements MetricsInterface
{
    use MetricsTrait;

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
