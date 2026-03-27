<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class GraphReport implements ReportInterface
{
    use ReportTrait;

    public function __construct(
        Config $config,
        private readonly DataProviderFactory $dataProviderFactory,
        protected readonly false|\DateTimeImmutable $historyDate,
        protected readonly FilesystemLoader $twigLoader,
        protected readonly Environment $twig,
        private readonly CliOutput $output)
    {
        $this->reportSubDirName = 'graph';
        $reportDir = $config->get('reportDir');
        $this->outputDir = (is_string($reportDir) ? $reportDir : '').DIRECTORY_SEPARATOR.$this->getReportSubDir().DIRECTORY_SEPARATOR;

        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }
    }

    public function generate(): void
    {
        $this->output->outWithMemory('Generating Graph report...');

        $graphDataProvider = $this->dataProviderFactory->getGraphDataProvider();
        $graphData = $graphDataProvider->getGraphData();

        $output = [
            'version' => '1.0',
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'nodes' => $graphData['nodes'],
            'edges' => $graphData['edges'],
            'clusters' => $graphData['clusters'],
            'cycles' => $graphData['cycles'],
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->outputDir.'graph.json', $json);

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $this->output->outNl($formatter->success('Graph report written to graph/graph.json'));
        $this->output->outNl();
    }
}
