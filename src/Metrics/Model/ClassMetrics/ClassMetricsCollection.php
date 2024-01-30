<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\ClassMetrics;


use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionTrait;

class ClassMetricsCollection implements MetricsCollectionInterface
{
    use MetricsCollectionTrait;

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
