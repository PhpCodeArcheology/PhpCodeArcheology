<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Marcus\PhpLegacyAnalyzer\Analysis\CyclomaticComplexityVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\LocVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
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
        $idVisitor = new IdentifyVisitor($this->metrics);
        $locVisitor = new LocVisitor($this->metrics);
        $cyCoVisitor = new CyclomaticComplexityVisitor($this->metrics);

        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($idVisitor);
        $this->traverser->addVisitor($locVisitor);
        $this->traverser->addVisitor($cyCoVisitor);

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('overallFiles', count($fileList->getFiles()));
        $this->metrics->set('project', $projectMetrics);

        foreach ($fileList->getFiles() as $file) {
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
            } catch (\PhpParser\Error $e) {
                $fileMetrics->set('error', $e->getMessage());
                echo "Parse error in file $file: " . $e->getMessage();
            }

            if (! $ast) {
                continue;
            }

            $this->traverser->traverse($ast);
        }

        echo PHP_EOL;
        foreach ($this->metrics->getAll() as $metric) {
            if (! $metric instanceof ProjectMetrics) {
                continue;
            }

            foreach ($metric->getAll() as $key => $m) {
                echo "$key: $m".PHP_EOL;
            }
            /*
            echo $metric->getName() . PHP_EOL;
            if ($metric instanceof FileMetrics) {
                echo "- lloc: " . $metric->get('lloc') . PHP_EOL;
                echo "- lloc outside: " . $metric->get('llocOutside') . PHP_EOL;
                echo "- cyclo: " . $metric->get('cc') . PHP_EOL;
            }
            elseif ($metric instanceof ClassMetrics || $metric instanceof FunctionMetrics) {
                echo "- lloc: " . $metric->get('lloc') . PHP_EOL;
                echo "- cyclo: " . $metric->get('cc') . PHP_EOL;

                if ($metric instanceof ClassMetrics) {
                    echo '- methods: ' . $metric->get('methodCount') . PHP_EOL;
                    echo '- private methods: ' . $metric->get('privateMethods') . PHP_EOL;
                    echo '- public methods: ' . $metric->get('publicMethods') . PHP_EOL;
                    echo '- static methods: ' . $metric->get('staticMethods') . PHP_EOL;
                    foreach ($metric->get('methods') as $method) {
                        echo '  - ' . $method->getName() . PHP_EOL;
                        echo '    - lloc: ' . $method->get('lloc') . PHP_EOL;
                        echo "    - cyclo: " . $method->get('cc') . PHP_EOL;
                    }
                }
            }
            */
        }
    }
}