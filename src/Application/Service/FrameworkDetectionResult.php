<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class FrameworkDetectionResult
{
    public function __construct(
        public bool $doctrineDetected = false,
        public bool $symfonyDetected = false,
        public bool $laravelDetected = false,
        public string $composerJsonPath = '',
    ) {
    }

    public function hasAnyFramework(): bool
    {
        return $this->doctrineDetected || $this->symfonyDetected || $this->laravelDetected;
    }

    public function getDetectedNames(): array
    {
        $names = [];
        if ($this->symfonyDetected) $names[] = 'Symfony';
        if ($this->laravelDetected) $names[] = 'Laravel';
        if ($this->doctrineDetected) $names[] = 'Doctrine';
        return $names;
    }

    public function getSummary(): string
    {
        $names = $this->getDetectedNames();
        return empty($names) ? '' : implode(' + ', $names);
    }
}
