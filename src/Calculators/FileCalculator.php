<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsEnum;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;

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

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $commonPath,
            'commonPath'
        );

        foreach ($this->files as $key => $fileName) {
            $projectPath = $fileName;
            if ($commonPath) {
                $projectPath = str_replace($commonPath, '', $fileName);
            }
            $pathInfo = pathinfo($projectPath);

            $fileMetrics = [
                'fullName' => $fileName,
                'projectPath' => $projectPath,
                'dirName' => $pathInfo['dirname'],
                'fileName' => $pathInfo['basename'],
            ];

            $this->metricsController->setMetricValues(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $fileName],
                $fileMetrics
            );
        }

        foreach ($this->metricsController->getAllCollections() as &$metrics) {
            if ($metrics instanceof FileMetricsCollection) {
                continue;
            }

            $value = MetricValue::ofValueAndTypeKey(
                str_replace($commonPath, '', $metrics->getPath()),
                'filePath'
            );

            $metrics->set('filePath', $value);
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
