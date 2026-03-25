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

        $hasDoctrine = false;
        foreach ($allDeps as $dep) {
            if (str_starts_with($dep, 'doctrine/')) {
                $hasDoctrine = true;
                break;
            }
        }

        return new FrameworkDetectionResult(
            doctrineDetected: $hasDoctrine,
            symfonyDetected: in_array('symfony/framework-bundle', $allDeps, true),
            laravelDetected: in_array('laravel/framework', $allDeps, true),
            composerJsonPath: $composerJsonPath,
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
}
