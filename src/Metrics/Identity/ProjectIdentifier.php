<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Identity;

class ProjectIdentifier implements IdentifierInterface
{
    private string $identifier;

    private function __construct(string $path)
    {
        $this->identifier = 'x' . hash('crc32', $path);
    }

    public static function ofPath(string $path): ProjectIdentifier
    {
        return new ProjectIdentifier($path);
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

    public function equals(ProjectIdentifier $other): bool
    {
        return $this->identifier === (string) $other;
    }
}
