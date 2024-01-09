<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Identity;

readonly class PackageIdentifier implements IdentifierInterface
{
    private function __construct(private string $name)
    {
    }

    public static function ofNamespace(string $name): PackageIdentifier
    {
        return new PackageIdentifier($name);
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function equals(PackageIdentifier $other): bool
    {
        return $this->name === (string) $other;
    }
}
