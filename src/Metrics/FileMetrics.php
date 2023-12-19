<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;


class FileMetrics implements MetrixInterface
{
    use MetricsTrait;

    private IdentifierInterface $identifier;

    public function __construct(
        private string $path
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