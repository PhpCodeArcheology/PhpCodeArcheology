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
use PhpCodeArch\Analysis\VisitorInterface;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\Manager\MetricsManager;
use PhpCodeArch\Metrics\Metrics;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

readonly class Analyzer
{
    public function __construct(
        private Config $config,
        private Parser $parser,
        private NodeTraverser $traverser,
        private Metrics $metrics,
        private MetricsManager $metricsManager,
        private CliOutput $output,
    )
    {
    }

    public function analyze(FileList $fileList): void
    {
        $visitorList = $this->getVisitorClassList();

        $this->traverser->addVisitor(new NameResolver());

        $visitorObjects = [];
        foreach ($visitorList as $visitor) {
            /**
             * @var VisitorInterface|NodeVisitor $visitorClass
             */
            $visitorClass = $visitor['class'];
            $usedMetricTypes = $this->metricsManager->getMetricTypesByKeys($visitor['metricTypeKeys']);

            $visitorObject = new $visitorClass(
                metrics: $this->metrics,
                usedMetricTypes: $usedMetricTypes,
            );

            $this->traverser->addVisitor($visitorObject);
            $visitorObjects[] = $visitorObject;
        }

        $fileCount = count($fileList->getFiles());

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('OverallFiles', $fileCount);
        $projectFileErrors = $projectMetrics->get('OverallFileErrors') ?? 0;

        $fileCount = number_format($fileCount);

        foreach ($fileList->getFiles() as $count => $file) {
            $this->output->cls();
            $this->output->out(
                "Analysing file \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$fileCount\033[0m... (" .
                ($projectFileErrors > 0 ? "\033[31m" : '') .
                $projectFileErrors .
                " errors\033[0m) " .
                memory_get_usage() . " bytes of memory"
            );

            foreach ($visitorObjects as $visitor) {
                $visitor->setPath($file);
            }

            $phpCode = file_get_contents($file);
            $encoding = mb_detect_encoding($phpCode);

            if ($encoding !== 'UFT-8') {
                $phpCode = mb_convert_encoding($phpCode, 'UTF-8');
            }

            $fileMetrics = new FileMetrics($file);
            $fileMetrics->set('originalEncoding', $encoding);
            $fileMetrics->set('errors', []);
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

        $projectMetrics->set('OverallFileErrors', $projectFileErrors);
        $this->metrics->set('project', $projectMetrics);
    }

    /**
     * @return array
     */
    private function getVisitorClassList(): array
    {
        return [
            [
                'class' => IdentifyVisitor::class,
                'metricTypeKeys' => [],
            ],
            [
                'class' => LocVisitor::class,
                'metricTypeKeys' => [
                    'loc',
                    'lloc',
                    'cloc',
                    'htmlLoc',
                    'llocOutside',
                ],
            ],
            [
                'class' => GlobalsVisitor::class,
                'metricTypeKeys' => [
                    'superglobals',
                    'variables',
                    'constants',
                ],
            ],
            [
                'class' => CyclomaticComplexityVisitor::class,
                'metricTypeKeys' => [
                    'cc',
                ],
            ],
            [
                'class' => DependencyVisitor::class,
                'metricTypeKeys' => [],
            ],
            [
                'class' => HalsteadMetricsVisitor::class,
                'metricTypeKeys' => [
                    'vocabulary',
                    'length',
                    'calcLength',
                    'volume',
                    'difficulty',
                    'effort',
                    'operators',
                    'operands',
                    'uniqueOperators',
                    'uniqueOperands',
                    'complexityDensity',
                ],
            ],
            [
                'class' => MaintainabilityIndexVisitor::class,
                'metricTypeKeys' => [
                    'maintainabilityIndex',
                    'maintainabilityIndexWithoutComments',
                    'commentWeight',
                ],
            ],
            [
                'class' => LcomVisitor::class,
                'metricTypeKeys' => [
                    'lcom',
                ],
            ],
            [
                'class' => PackageVisitor::class,
                'metricTypeKeys' => [],
            ],
        ];
    }
}
