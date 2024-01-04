<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Metrics;


class FunctionMetrics implements MetricsInterface
{
    use MetricsTrait;
    private IdentifierInterface $identifier;

    public function __construct(
        private readonly string $path,
        private readonly string $name
    )
    {
        $this->identifier = FunctionAndClassIdentifier::ofNameAndPath($this->name, $this->path);
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
