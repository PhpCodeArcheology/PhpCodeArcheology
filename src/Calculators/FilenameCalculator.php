<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Calculators;

use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

class FilenameCalculator implements CalculatorInterface
{

    public function calculate(Metrics $metrics): void
    {
        $files = [];

        foreach ($metrics->getAll() as $key => $metric) {
            if (! $metric instanceof FileMetrics) {
                continue;
            }

            $fileName = $metric->getName();
            $files[$key] = $fileName;
        }

        $commonPath = $this->findCommonPath($files);
        if (! str_ends_with($commonPath, '/')) {
            $commonPath .= '/';
        }

        foreach ($files as $key => $fileName) {
            $projectPath = str_replace($commonPath, '', $fileName);
            $pathInfo = pathinfo($projectPath);

            $fileMetric = $metrics->get($key);
            $fileMetric->set('fullName', $fileName);
            $fileMetric->set('projectPath', $projectPath);
            $fileMetric->set('dirName', $pathInfo['dirname']);
            $fileMetric->set('fileName', $pathInfo['basename']);
            $fileMetric->set('projectPath', $projectPath);
            $metrics->set($key, $fileMetric);
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
