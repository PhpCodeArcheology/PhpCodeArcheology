<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;

class FileCalculator implements CalculatorInterface
{
    use \PhpCodeArch\Metrics\Controller\Traits\MetricsReaderWriterTrait;

    /** @var array<string, string> identifier → fileName */
    private array $files = [];

    public function calculate(MetricsCollectionInterface $metrics): void
    {
        if (!$metrics instanceof FileMetricsCollection) {
            return;
        }

        $fileName = $metrics->getName();
        $this->files[(string) $metrics->getIdentifier()] = $fileName;
    }

    public function afterTraverse(): void
    {
        $commonPath = $this->findCommonPath($this->files);

        if ($commonPath && !str_ends_with($commonPath, '/')) {
            $commonPath .= '/';
        }

        $this->writer->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $commonPath,
            MetricKey::COMMON_PATH
        );

        foreach ($this->files as $fileName) {
            $projectPath = $fileName;
            if ('' !== $commonPath && '0' !== $commonPath) {
                $projectPath = str_replace($commonPath, '', $fileName);
            }
            $pathInfo = pathinfo((string) $projectPath);

            $fileMetrics = [
                MetricKey::FULL_NAME => $fileName,
                MetricKey::PROJECT_PATH => $projectPath,
                MetricKey::DIR_NAME => $pathInfo['dirname'] ?? '',
                MetricKey::FILE_NAME => $pathInfo['basename'],
            ];

            $this->writer->setMetricValues(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $fileName],
                $fileMetrics
            );
        }

        foreach ($this->registry->getAllCollections() as &$metrics) {
            if ($metrics instanceof FileMetricsCollection) {
                continue;
            }

            $value = MetricValue::ofValueAndTypeKey(
                str_replace($commonPath, '', $metrics->getPath()),
                MetricKey::FILE_PATH
            );

            $metrics->set(MetricKey::FILE_PATH, $value);
        }
    }

    /** @param array<string, string> $files */
    private function findCommonPath(array $files): string
    {
        if ([] === $files) {
            return '';
        }

        $pathParts = array_values(array_map(fn ($path): array => explode('/', (string) $path), $files));
        $commonPath = [];
        $pathCount = count($pathParts);

        foreach ($pathParts[0] as $i => $part) {
            for ($j = 1; $j < $pathCount; ++$j) {
                if (!isset($pathParts[$j][$i]) || $pathParts[$j][$i] !== $part) {
                    return implode('/', $commonPath);
                }
            }
            $commonPath[] = $part;
        }

        return implode('/', $commonPath);
    }
}
