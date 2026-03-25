<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class FrameworkDetector
{
    public function detect(string $projectRoot): FrameworkDetectionResult
    {
        $composerJsonPath = $this->findComposerJson($projectRoot);

        if ($composerJsonPath === null) {
            return new FrameworkDetectionResult();
        }

        $content = @file_get_contents($composerJsonPath);
        if ($content === false) {
            return new FrameworkDetectionResult(composerJsonPath: $composerJsonPath);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new FrameworkDetectionResult(composerJsonPath: $composerJsonPath);
        }

        $allDeps = array_merge(
            array_keys($data['require'] ?? []),
            array_keys($data['require-dev'] ?? [])
        );

        $devDeps = array_keys($data['require-dev'] ?? []);

        $hasDoctrine = false;
        foreach ($allDeps as $dep) {
            if (str_starts_with($dep, 'doctrine/')) {
                $hasDoctrine = true;
                break;
            }
        }

        $psr4Autoload = $this->parsePsr4Section($data['autoload']['psr-4'] ?? []);
        $psr4AutoloadDev = $this->parsePsr4Section($data['autoload-dev']['psr-4'] ?? []);

        return new FrameworkDetectionResult(
            doctrineDetected: $hasDoctrine,
            symfonyDetected: in_array('symfony/framework-bundle', $allDeps, true),
            laravelDetected: in_array('laravel/framework', $allDeps, true),
            composerJsonPath: $composerJsonPath,
            phpunitDetected: in_array('phpunit/phpunit', $devDeps, true),
            pestDetected: in_array('pestphp/pest', $devDeps, true),
            codeceptionDetected: in_array('codeception/codeception', $devDeps, true),
            psr4Autoload: $psr4Autoload,
            psr4AutoloadDev: $psr4AutoloadDev,
        );
    }

    private function findComposerJson(string $startDir): ?string
    {
        $dir = realpath($startDir) ?: $startDir;

        for ($i = 0; $i < 10; $i++) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'composer.json';
            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }

    private function parsePsr4Section(array $psr4Data): array
    {
        $result = [];
        foreach ($psr4Data as $namespace => $paths) {
            $namespace = rtrim((string) $namespace, '\\') . '\\';
            if (is_string($paths)) {
                $paths = [$paths];
            }
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    $result[$namespace] = rtrim((string) $path, '/');
                }
            }
        }
        return $result;
    }
}
