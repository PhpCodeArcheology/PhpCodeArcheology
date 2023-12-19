<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Error;
use Marcus\PhpLegacyAnalyzer\Analysis\LocVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
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
    )
    {
    }

    public function analyze(FileList $fileList): void
    {
        $locVisitor = new LocVisitor($this->metrics);

        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($locVisitor);

        foreach ($fileList->getFiles() as $file) {
            $locVisitor->setPath($file);

            $phpCode = file_get_contents($file);
            $encoding = mb_detect_encoding($phpCode);

            if ($encoding !== 'UFT-8') {
                $phpCode = mb_convert_encoding($phpCode, 'UTF-8');
            }

            $loc = count(preg_split('/\r\n|\r|\n/', $phpCode));

            $fileMetrics = new FileMetrics($file);

            $ast = null;
            try {
                $ast = $this->parser->parse($phpCode);
            } catch (\PhpParser\Error $e) {
                $fileMetrics->set('error', $e->getMessage());
                echo "Parse error in file $file: " . $e->getMessage();
            }

            $fileMetrics->set('loc', $loc);
            $fileMetrics->set('originalEncoding', $encoding);
            $this->metrics->push($fileMetrics);

            if (! $ast) {
                continue;
            }

            $this->traverser->traverse($ast);
        }

        foreach ($this->metrics->getAll() as $metric) {
            //echo $metric->getName() . PHP_EOL;
        }
    }
}