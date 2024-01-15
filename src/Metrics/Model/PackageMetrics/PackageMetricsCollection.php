<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\PackageMetrics;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Identity\PackageIdentifier;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionTrait;

class PackageMetricsCollection implements MetricsCollectionInterface
{
    use MetricsCollectionTrait;

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
