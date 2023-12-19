<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;


class ClassMetrics implements MetricsInterface
{
    use MetricsTrait;

    private IdentifierInterface $identifier;

    public function __construct(
        private string $path,
        private string $name
    )
    {
        $this->identifier = FunctionAndClassIdentifier::ofNameAndPath($this->path, $this->name);
    }

    public function getIdentifier(): IdentifierInterface
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }
}