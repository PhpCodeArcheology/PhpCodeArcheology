<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Identity;

readonly class FunctionAndClassIdentifier implements IdentifierInterface
{
    private string $identifier;

    private function __construct(string $name, string $path)
    {
        $this->identifier = hash('sha256', $path . $name);
    }

    public static function ofNameAndPath(string $name, string $path): FunctionAndClassIdentifier
    {
        return new FunctionAndClassIdentifier($name, $path);
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

    public function equals(FunctionAndClassIdentifier $other): bool
    {
        return $this->identifier === (string) $other;
    }
}
