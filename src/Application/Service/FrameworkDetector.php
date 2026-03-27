<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class FrameworkDetector
{
    public function detect(string $projectRoot): FrameworkDetectionResult
    {
        $composerJsonPath = $this->findComposerJson($projectRoot);

        if (null === $composerJsonPath) {
            return new FrameworkDetectionResult();
        }

        $content = @file_get_contents($composerJsonPath);
        if (false === $content) {
            return new FrameworkDetectionResult(composerJsonPath: $composerJsonPath);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new FrameworkDetectionResult(composerJsonPath: $composerJsonPath);
        }

        $require = is_array($data['require'] ?? null) ? $data['require'] : [];
        $requireDev = is_array($data['require-dev'] ?? null) ? $data['require-dev'] : [];
        $allDeps = array_merge(array_keys($require), array_keys($requireDev));
        $devDeps = array_keys($requireDev);

        $hasDoctrine = false;
        foreach ($allDeps as $dep) {
            if (str_starts_with((string) $dep, 'doctrine/')) {
                $hasDoctrine = true;
                break;
            }
        }

        $autoload = is_array($data['autoload'] ?? null) ? $data['autoload'] : [];
        $autoloadDev = is_array($data['autoload-dev'] ?? null) ? $data['autoload-dev'] : [];
        $psr4Data = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];
        $psr4DataDev = is_array($autoloadDev['psr-4'] ?? null) ? $autoloadDev['psr-4'] : [];
        $psr4Autoload = $this->parsePsr4Section($psr4Data);
        $psr4AutoloadDev = $this->parsePsr4Section($psr4DataDev);

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

        for ($i = 0; $i < 10; ++$i) {
            $candidate = $dir.DIRECTORY_SEPARATOR.'composer.json';
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

    /**
     * @param array<mixed> $psr4Data
     *
     * @return array<string, string>
     */
    private function parsePsr4Section(array $psr4Data): array
    {
        $result = [];
        foreach ($psr4Data as $namespace => $paths) {
            $namespace = rtrim((string) $namespace, '\\').'\\';
            if (is_string($paths)) {
                $paths = [$paths];
            }
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (!is_string($path)) {
                        continue;
                    }
                    $result[$namespace] = rtrim($path, '/');
                }
            }
        }

        return $result;
    }
}
