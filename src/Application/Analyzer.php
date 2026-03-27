<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Analysis\CognitiveComplexityVisitor;
use PhpCodeArch\Analysis\ConfigAwareVisitorInterface;
use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\DeadCodeVisitor;
use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Analysis\DocumentationCoverageVisitor;
use PhpCodeArch\Analysis\GlobalsVisitor;
use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\InitializableVisitorInterface;
use PhpCodeArch\Analysis\LcomVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Analysis\PackageVisitor;
use PhpCodeArch\Analysis\RuntimeComplexityVisitor;
use PhpCodeArch\Analysis\SecuritySmellVisitor;
use PhpCodeArch\Analysis\TypeCoverageVisitor;
use PhpCodeArch\Analysis\VisitorInterface;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\ErrorCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

readonly class Analyzer
{
    public function __construct(
        private Config $config,
        private Parser $parser,
        private NodeTraverser $traverser,
        private MetricsController $metricsController,
        private CliOutput $output,
    ) {
    }

    public function analyze(FileList $fileList): void
    {
        $visitorObjects = $this->getVisitorObjects();

        $fileCount = count($fileList->getFiles());
        $projectFileErrors = $this->traverseFiles($fileList, $visitorObjects);

        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                MetricKey::OVERALL_FILES => $fileCount,
                MetricKey::OVERALL_FILE_ERRORS => $projectFileErrors,
            ]
        );
    }

    /** @return array<class-string<VisitorInterface&\PhpParser\NodeVisitor>> */
    private function getVisitorClassList(): array
    {
        if ($this->config->get('quickMode')) {
            return [
                IdentifyVisitor::class,
                LocVisitor::class,
                CyclomaticComplexityVisitor::class,
                CognitiveComplexityVisitor::class,
            ];
        }

        return [
            IdentifyVisitor::class,
            TypeCoverageVisitor::class,
            LocVisitor::class,
            GlobalsVisitor::class,
            CyclomaticComplexityVisitor::class,
            CognitiveComplexityVisitor::class,
            DependencyVisitor::class,
            HalsteadMetricsVisitor::class,
            LcomVisitor::class,
            DocumentationCoverageVisitor::class,
            DeadCodeVisitor::class,
            SecuritySmellVisitor::class,
            RuntimeComplexityVisitor::class,
            PackageVisitor::class,
        ];
    }

    /** @return array<VisitorInterface&\PhpParser\NodeVisitor> */
    private function getVisitorObjects(): array
    {
        $visitorList = $this->getVisitorClassList();

        $this->traverser->addVisitor(new NameResolver());

        $visitorObjects = [];
        foreach ($visitorList as $visitorClass) {
            $visitorObject = new $visitorClass(
                metricsController: $this->metricsController
            );

            if ($visitorObject instanceof InitializableVisitorInterface) {
                $visitorObject->init();
            }

            if ($visitorObject instanceof ConfigAwareVisitorInterface) {
                $visitorObject->injectConfig($this->config);
            }

            $this->traverser->addVisitor($visitorObject);
            $visitorObjects[] = $visitorObject;
        }

        return $visitorObjects;
    }

    /** @param array<VisitorInterface&\PhpParser\NodeVisitor> $visitorObjects */
    private function traverseFiles(FileList $fileList, array $visitorObjects): int
    {
        $fileCount = count($fileList->getFiles());
        $projectFileErrors = 0;

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $progressBar = new ProgressBar($this->output, $formatter, $fileCount, 'Analysing');

        $fileNameCollection = new FileNameCollection();
        $phpConfig = $this->config->get('php');
        $shortOpenTags = is_array($phpConfig) ? ($phpConfig['shortOpenTags'] ?? false) : false;
        $errorFiles = [];

        foreach ($fileList->getFiles() as $count => $file) {
            $progressBar->advance();

            foreach ($visitorObjects as $visitor) {
                $visitor->setPath($file);
            }

            $phpCode = @file_get_contents($file);

            if ($shortOpenTags && is_string($phpCode)) {
                $phpCode = preg_replace('/<\?(?!php|=)/', '<?php ', $phpCode) ?? $phpCode;
            }

            $fileErrorCollection = new ErrorCollection();

            $fileMetricCollection = $this->metricsController->createMetricCollection(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $file]
            );

            $fileNameCollection->set($file, (string) $fileMetricCollection->getIdentifier());

            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $file],
                $fileErrorCollection,
                'errors'
            );

            if (!is_string($phpCode)) {
                $fileErrorCollection->set("Could not read file: $file");
                $errorFiles[] = [$file, 'Could not read file'];
                ++$projectFileErrors;
                continue;
            }

            $encoding = mb_detect_encoding($phpCode, 'UTF-8, ISO-8859-1, Windows-1252', true);
            if (false !== $encoding && 'UTF-8' !== $encoding) {
                $converted = mb_convert_encoding($phpCode, 'UTF-8', $encoding);
                if (false !== $converted) {
                    $phpCode = $converted;
                }
            }

            $ast = null;

            try {
                $ast = $this->parser->parse($phpCode);
            } catch (Error $e) {
                $fileErrorCollection->set($e->getMessage());
                $errorFiles[] = [$file, $e->getMessage()];
                ++$projectFileErrors;
            }

            unset($phpCode);

            if (!$ast) {
                continue;
            }

            $this->traverser->traverse($ast);
            unset($ast);

            if (0 === $count % 100) {
                gc_collect_cycles();
            }
        }

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $fileNameCollection,
            'files'
        );

        $progressBar->finish();

        if ($projectFileErrors > 0) {
            $this->output->outNl(
                $formatter->warning("Warning: $projectFileErrors file(s) had errors and were skipped:")
            );
            $filesConfig = $this->config->get('files');
            $firstFile = is_array($filesConfig) ? ($filesConfig[0] ?? '') : '';
            $commonPath = is_string($firstFile) ? $firstFile : '';
            foreach ($errorFiles as [$errorFile, $errorMessage]) {
                $displayPath = str_starts_with((string) $errorFile, $commonPath)
                    ? substr((string) $errorFile, strlen($commonPath) + 1)
                    : $errorFile;
                $this->output->outNl('  '.$formatter->dim($displayPath).': '.$errorMessage);
            }
        }

        $this->output->outNl(
            'Analysed '.$formatter->success((string) ($fileCount - $projectFileErrors)).
            ' of '.$formatter->success((string) $fileCount).' files successfully.'
        );

        return $projectFileErrors;
    }
}
