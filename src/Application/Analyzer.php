<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Marcus\PhpLegacyAnalyzer\Analysis\CyclomaticComplexityVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\LocVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
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
        private Metrics $metrics,
        private CliOutput $output,
    )
    {
    }

    public function analyze(FileList $fileList): void
    {
        $idVisitor = new IdentifyVisitor($this->metrics);
        $locVisitor = new LocVisitor($this->metrics);
        $cyCoVisitor = new CyclomaticComplexityVisitor($this->metrics);

        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($idVisitor);
        $this->traverser->addVisitor($locVisitor);
        $this->traverser->addVisitor($cyCoVisitor);

        $fileCount = count($fileList->getFiles());

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('overallFiles', $fileCount);
        $projectFileErrors = $projectMetrics->get('overallFileErrors');

        $fileCount = number_format($fileCount);

        foreach ($fileList->getFiles() as $count => $file) {
            $this->output->cls();
            $this->output->out(
                "Analysing file \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$fileCount\033[0m... (" .
                ($projectFileErrors > 0 ? "\033[31m" : '') .
                $projectFileErrors .
                " errors\033[0m)"
            );

            $idVisitor->setPath($file);
            $locVisitor->setPath($file);
            $cyCoVisitor->setPath($file);

            $phpCode = file_get_contents($file);
            $encoding = mb_detect_encoding($phpCode);

            if ($encoding !== 'UFT-8') {
                $phpCode = mb_convert_encoding($phpCode, 'UTF-8');
            }

            $fileMetrics = new FileMetrics($file);
            $fileMetrics->set('originalEncoding', $encoding);
            $this->metrics->push($fileMetrics);

            $ast = null;

            try {
                $ast = $this->parser->parse($phpCode);
            } catch (Error $e) {
                $fileErrors = $fileMetrics->get('errors') ?? [];
                $fileErrors[] = $e->getMessage();
                $fileMetrics->set('errors', $fileErrors);
                ++ $projectFileErrors;
            }

            if (! $ast) {
                continue;
            }

            $this->traverser->traverse($ast);
        }

        $this->output->outNl();

        $projectMetrics->set('overallFileErrors', $projectFileErrors);
        $this->metrics->set('project', $projectMetrics);

        foreach ($this->metrics->getAll() as $metric) {
        }
    }
}