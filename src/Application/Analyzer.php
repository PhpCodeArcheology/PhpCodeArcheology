<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Analysis\GlobalsVisitor;
use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LcomVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Analysis\MaintainabilityIndexVisitor;
use PhpCodeArch\Analysis\PackageVisitor;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ErrorCollection;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Repository\RepositoryInterface;
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
        private RepositoryInterface $repository,
        private CliOutput         $output,
    )
    {
    }

    public function analyze(FileList $fileList): void
    {
        $visitorObjects = $this->getVisitorObjects();

        $fileCount = count($fileList->getFiles());
        $projectFileErrors = $this->traverseFiles($fileList, $visitorObjects);

        $this->repository->saveMetricValues(
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
        return [
            IdentifyVisitor::class,
            LocVisitor::class,
            GlobalsVisitor::class,
            CyclomaticComplexityVisitor::class,
            DependencyVisitor::class,
            HalsteadMetricsVisitor::class,
            MaintainabilityIndexVisitor::class,
            LcomVisitor::class,
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
                repository: $this->repository
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

        $fileNameCollection = new FileNameCollection();

        foreach ($fileList->getFiles() as $count => $file) {
            $this->progressOutput($count, $fileCount, $projectFileErrors);

            foreach ($visitorObjects as $visitor) {
                $visitor->setPath($file);
            }

            $phpCode = file_get_contents($file);
            $encoding = mb_detect_encoding($phpCode);
            if ($encoding !== 'UFT-8') {
                $phpCode = mb_convert_encoding($phpCode, 'UTF-8');
            }

            $fileErrorCollection = new ErrorCollection();

            $fileMetricCollection = $this->repository->createMetricCollection(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $file]
            );

            $fileNameCollection->set($file, (string) $fileMetricCollection->getIdentifier());

            $this->repository->saveCollection(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $file],
                $fileErrorCollection,
                'errors'
            );

            $ast = null;

            try {
                $ast = $this->parser->parse($phpCode);
            } catch (Error $e) {
                $fileErrorCollection->set($e->getMessage());
                ++ $projectFileErrors;
            }

            if (! $ast) {
                continue;
            }

            $this->traverser->traverse($ast);
        }

        $this->repository->saveCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $fileNameCollection,
            'files'
        );

        $this->output->outNl();

        return $projectFileErrors;
    }

    private function progressOutput(int $count, int $fileCount, int $projectFileErrors): void
    {
        $this->output->cls();
        $this->output->outWithMemory(
            "Analysing file \033[34m" .
            number_format($count + 1) .
            "\033[0m of \033[32m$fileCount\033[0m... (" .
            ($projectFileErrors > 0 ? "\033[31m" : '') .
            $projectFileErrors .
            " errors\033[0m)"
        );
    }
}
