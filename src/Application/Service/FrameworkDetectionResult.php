<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

final readonly class FrameworkDetectionResult
{
    /**
     * @param array<string, string> $psr4Autoload
     * @param array<string, string> $psr4AutoloadDev
     */
    public function __construct(
        public bool $doctrineDetected = false,
        public bool $symfonyDetected = false,
        public bool $laravelDetected = false,
        public string $composerJsonPath = '',
        public bool $phpunitDetected = false,
        public bool $pestDetected = false,
        public bool $codeceptionDetected = false,
        public array $psr4Autoload = [],
        public array $psr4AutoloadDev = [],
    ) {
    }

    public function hasAnyFramework(): bool
    {
        return $this->doctrineDetected || $this->symfonyDetected || $this->laravelDetected;
    }

    public function hasTestFramework(): bool
    {
        return $this->phpunitDetected || $this->pestDetected || $this->codeceptionDetected;
    }

    /** @return string[] */
    public function getDetectedNames(): array
    {
        $names = [];
        if ($this->symfonyDetected) {
            $names[] = 'Symfony';
        }
        if ($this->laravelDetected) {
            $names[] = 'Laravel';
        }
        if ($this->doctrineDetected) {
            $names[] = 'Doctrine';
        }

        return $names;
    }

    /** @return string[] */
    public function getTestFrameworkNames(): array
    {
        $names = [];
        if ($this->phpunitDetected) {
            $names[] = 'PHPUnit';
        }
        if ($this->pestDetected) {
            $names[] = 'Pest';
        }
        if ($this->codeceptionDetected) {
            $names[] = 'Codeception';
        }

        return $names;
    }

    public function getSummary(): string
    {
        $names = $this->getDetectedNames();

        return implode(' + ', $names);
    }

    public function getTestFrameworkSummary(): string
    {
        $names = $this->getTestFrameworkNames();

        return implode(' + ', $names);
    }
}
