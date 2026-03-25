<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Analysis\CognitiveComplexityVisitor;
use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\DeadCodeVisitor;
use PhpCodeArch\Analysis\DocumentationCoverageVisitor;
use PhpCodeArch\Analysis\RuntimeComplexityVisitor;
use PhpCodeArch\Analysis\SecuritySmellVisitor;
use PhpCodeArch\Analysis\TypeCoverageVisitor;
use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Analysis\GlobalsVisitor;
use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LcomVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Analysis\PackageVisitor;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ErrorCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

readonly class Analyzer
{
    public function __construct(
        private Config            $config,
        private Parser            $parser,
        private NodeTraverser     $traverser,
        private MetricsController $metricsController,
        private CliOutput         $output,
    )
    {
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
                'overallFiles' => $fileCount,
                'overallFileErrors' => $projectFileErrors,
            ]
        );
    }

    /**
     * @return array
     */
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

    private function getVisitorObjects(): array
    {
        $visitorList = $this->getVisitorClassList();

        $this->traverser->addVisitor(new NameResolver());

        $visitorObjects = [];
        foreach ($visitorList as $visitorClass) {
            $visitorObject = new $visitorClass(
                metricsController: $this->metricsController
            );

            if (method_exists($visitorObject, 'init')) {
                $visitorObject->init();
            }

            if (method_exists($visitorObject, 'injectConfig')) {
                $visitorObject->injectConfig($this->config);
            }

            $this->traverser->addVisitor($visitorObject);
            $visitorObjects[] = $visitorObject;
        }

        return $visitorObjects;
    }

    private function traverseFiles(FileList $fileList, array $visitorObjects): int
    {
        $fileCount = count($fileList->getFiles());
        $projectFileErrors = 0;

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $progressBar = new ProgressBar($this->output, $formatter, $fileCount, 'Analysing');

        $fileNameCollection = new FileNameCollection();
        $shortOpenTags = ($this->config->get('php') ?? [])['shortOpenTags'] ?? false;
        $errorFiles = [];

        foreach ($fileList->getFiles() as $count => $file) {
            $progressBar->advance();

            foreach ($visitorObjects as $visitor) {
                $visitor->setPath($file);
            }

            $phpCode = @file_get_contents($file);

            if ($shortOpenTags && $phpCode !== false) {
                $phpCode = preg_replace('/<\?(?!php|=)/', '<?php ', $phpCode);
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

            if ($phpCode === false) {
                $fileErrorCollection->set("Could not read file: $file");
                $errorFiles[] = [$file, 'Could not read file'];
                ++$projectFileErrors;
                continue;
            }

            $encoding = mb_detect_encoding($phpCode, 'UTF-8, ISO-8859-1, Windows-1252', true);
            if ($encoding !== false && $encoding !== 'UTF-8') {
                $phpCode = mb_convert_encoding($phpCode, 'UTF-8', $encoding);
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

            if (! $ast) {
                continue;
            }

            $this->traverser->traverse($ast);
            unset($ast);

            if ($count % 100 === 0) {
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
            $commonPath = $this->config->get('files')[0] ?? '';
            foreach ($errorFiles as [$errorFile, $errorMessage]) {
                $displayPath = str_starts_with($errorFile, $commonPath)
                    ? substr($errorFile, strlen($commonPath) + 1)
                    : $errorFile;
                $this->output->outNl('  ' . $formatter->dim($displayPath) . ': ' . $errorMessage);
            }
        }

        $this->output->outNl(
            'Analysed ' . $formatter->success((string) ($fileCount - $projectFileErrors)) .
            ' of ' . $formatter->success((string) $fileCount) . ' files successfully.'
        );

        return $projectFileErrors;
    }
}
