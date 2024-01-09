<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\PackageMetrics;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Identity\PackageIdentifier;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\MetricsTrait;

class PackageMetrics implements MetricsInterface
{
    use MetricsTrait;

    private PackageIdentifier $identifier;

    private string $path;

    public function __construct(
        private readonly string $name,
    )
    {
        $this->identifier = PackageIdentifier::ofNamespace($name);
        $this->path = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIdentifier(): IdentifierInterface
    {
       return $this->identifier;
    }
}
