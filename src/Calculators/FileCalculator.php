<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

class FileCalculator implements CalculatorInterface
{
    use CalculatorTrait;

    private array $files = [];

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (! $metrics instanceof FileMetricsCollection) {
            return;
        }

        $fileName = $metrics->getName();
        $this->files[(string) $metrics->getIdentifier()] = $fileName;
    }

    public function afterTraverse(): void
    {
        $commonPath = $this->findCommonPath($this->files);

        if ($commonPath && ! str_ends_with($commonPath, '/')) {
            $commonPath .= '/';
        }

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('commonPath', $commonPath);
        $this->metrics->set('project', $projectMetrics);

        foreach ($this->files as $key => $fileName) {
            $projectPath = $fileName;
            if ($commonPath) {
                $projectPath = str_replace($commonPath, '', $fileName);
            }
            $pathInfo = pathinfo($projectPath);

            $fileMetric = $this->metrics->get($key);

            $fileMetric->set('fullName', $fileName);
            $fileMetric->set('projectPath', $projectPath);
            $fileMetric->set('dirName', $pathInfo['dirname']);
            $fileMetric->set('fileName', $pathInfo['basename']);
            $fileMetric->set('projectPath', $projectPath);

            if (count($fileMetric->get('errors')) > 0 || ! $fileMetric->get('loc')) {
                $metrics = FileMetricsEnum::values();
                foreach ($metrics as $metricKey) {
                    $fileMetric->set($metricKey, 0);
                }
            }

            $this->metrics->set($key, $fileMetric);
        }

        foreach ($this->metrics->getAll() as &$metrics) {
            if (is_array($metrics) || $metrics instanceof FileMetricsCollection) {
                continue;
            }

            $metrics->set('filePath', str_replace($commonPath, '', $metrics->getPath()));
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
