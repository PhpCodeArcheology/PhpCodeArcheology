<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\MetricsInterface;

class FilenameCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private array $files = [];

    public function calculate(MetricsInterface $metrics): void
    {
        if (! $metrics instanceof FileMetrics) {
            return;
        }

        $fileName = $metrics->getName();
        $this->files[(string) $metrics->getIdentifier()] = $fileName;
    }

    public function afterTraverse(): void
    {
        $commonPath = $this->findCommonPath($this->files);
        if (! str_ends_with($commonPath, '/')) {
            $commonPath .= '/';
        }

        foreach ($this->files as $key => $fileName) {
            $projectPath = str_replace($commonPath, '', $fileName);
            $pathInfo = pathinfo($projectPath);

            $fileMetric = $this->metrics->get($key);
            $fileMetric->set('fullName', $fileName);
            $fileMetric->set('projectPath', $projectPath);
            $fileMetric->set('dirName', $pathInfo['dirname']);
            $fileMetric->set('fileName', $pathInfo['basename']);
            $fileMetric->set('projectPath', $projectPath);
            $this->metrics->set($key, $fileMetric);
        }
    }

    private function findCommonPath(array $files): string
    {
        if (empty($files)) {
            return '';
        }

        $pathParts = array_values(array_map(fn($path) => explode('/', $path), $files));
        $commonPath = [];
        $pathCount = count($pathParts);

        foreach ($pathParts[0] as $i => $part) {
            for ($j = 1; $j < $pathCount; $j++) {
                if (!isset($pathParts[$j][$i]) || $pathParts[$j][$i] !== $part) {
                    return implode('/', $commonPath);
                }
            }
            $commonPath[] = $part;
        }

        return implode('/', $commonPath);
    }
}
