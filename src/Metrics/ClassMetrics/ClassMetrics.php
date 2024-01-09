<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\ClassMetrics;


use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\MetricsInterface;
use PhpCodeArch\Metrics\MetricsTrait;

class ClassMetrics implements MetricsInterface
{
    use MetricsTrait;

    private IdentifierInterface $identifier;

    public function __construct(
        private string $path,
        private string $name
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
