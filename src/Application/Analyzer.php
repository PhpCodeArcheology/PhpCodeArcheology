<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ErrorCollection;
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
        $visitorList = $this->getVisitorClassList();

        $this->traverser->addVisitor(new NameResolver());

        $visitorObjects = [];
        foreach ($visitorList as $visitor) {
            $visitorClass = $visitor;

            $visitorObject = new $visitorClass(
                metricsController: $this->metricsController
            );

            $this->traverser->addVisitor($visitorObject);
            $visitorObjects[] = $visitorObject;
        }

        $fileCount = count($fileList->getFiles());

        $this->metricsController->setMetricValue(
          MetricCollectionTypeEnum::ProjectCollection,
          null,
          $fileCount,
          'overallFiles'
        );

        $projectFileErrors = $this->metricsController->getMetricsValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'overallFileErrors'
        )?->getValue() ?? 0;

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

            $fileErrorCollection = new ErrorCollection();

            $this->metricsController->createMetricCollection(
                MetricCollectionTypeEnum::FileCollection,
                ['path' => $file]
            );

            $this->metricsController->setCollection(
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

        //var_dump($this->metricsController);

        $this->output->outNl();

        $this->metricsController->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $projectFileErrors,
            'overallFileErrors'
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
            /*
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
            */
        ];
    }
}
