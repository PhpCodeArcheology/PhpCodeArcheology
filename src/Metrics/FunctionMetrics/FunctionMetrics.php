<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\FunctionMetrics;


use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\MetricsTrait;

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
