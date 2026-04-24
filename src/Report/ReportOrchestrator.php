<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\ClaudeMdGenerator;
use PhpCodeArch\Application\Service\HistoryService;
use PhpCodeArch\Application\Service\SummaryPrinter;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final class ReportOrchestrator
{
    /**
     * @param array<int, int> $problems
     */
    public function generateReports(
        Config $config,
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        MetricsRegistryInterface $registry,
        CliOutput $output,
        array $problems = [],
    ): void {
        $twigLoader = new FilesystemLoader();
        $isDebug = '1' === getenv('APP_DEBUG');
        $twig = new Environment($twigLoader, options: [
            'debug' => $isDebug,
        ]);
        if ($isDebug) {
            $twig->addExtension(new DebugExtension());
        }

        $dataProviderFactory = new DataProviderFactory($reader, $registry);

        $historyService = new HistoryService();
        $historyDate = $historyService->setDeltas($reader, $writer, $registry, $config);

        $reports = ReportFactory::createMultiple(
            $config,
            $dataProviderFactory,
            $historyDate,
            $twigLoader,
            $twig,
            $output
        );

        foreach ($reports as $report) {
            $report->generate();
        }

        // Migration hint for users upgrading from pre-v1.6.0
        $oldIndexFile = $config->getReportDir().DIRECTORY_SEPARATOR.'index.html';
        if (file_exists($oldIndexFile)) {
            $output->outNl();
            $output->outNl('Note: Since v1.6.0, reports are generated in subdirectories (e.g., html/, json/).');
            $output->outNl('Old report files in the root directory can be safely removed.');
            $output->outNl();
        }

        $historyService->writeHistory($reader, $registry, $config);

        if ($config->get('generateClaudeMd')) {
            (new ClaudeMdGenerator())->generate($config, $dataProviderFactory, $output);
        }

        $formatter = $output->getFormatter() ?? new CliFormatter();
        (new SummaryPrinter())->print($reader, $config, $problems, $output, $formatter);

        $frameworkDetection = $config->getFrameworkDetection();
        if (null !== $frameworkDetection
            && $frameworkDetection->hasTestFramework()
            && null === $config->get('coverageFile')) {
            $output->outNl();
            $output->outNl('Tip: For precise line-level coverage data, generate a Clover XML report first:');
            if ($frameworkDetection->pestDetected) {
                $output->outNl('  '.$formatter->info('XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml'));
            }
            if ($frameworkDetection->phpunitDetected) {
                $output->outNl('  '.$formatter->info('XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover clover.xml'));
            }
            $output->outNl('  Requires Xdebug or PCOV PHP extension.');
            $output->outNl('  The clover.xml will be detected automatically on the next run.');
        }
    }
}
