<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Identity;

readonly class FileIdentifier implements IdentifierInterface
{
    private string $identifier;

    private function __construct(string $path)
    {
        $this->identifier = 'x' . hash('crc32', $path);
    }

    public static function ofPath(string $path): FileIdentifier
    {
        return new FileIdentifier($path);
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

    public function equals(FileIdentifier $other): bool
    {
        return $this->identifier === (string) $other;
    }
}
