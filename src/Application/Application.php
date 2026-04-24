<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Mcp\Command\McpCommand;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Report\ReportTypeNotSupported;

final readonly class Application implements AnalysisPipelineInterface
{
    /** @deprecated Use Version::CURRENT instead */
    public const VERSION = Version::CURRENT;

    public function __construct(
        private ServiceFactory $factory = new ServiceFactory(),
    ) {
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): int
    {
        try {
            $config = $this->createConfig($argv);
        } catch (HelpDisplayException) {
            echo $this->getHelpText().PHP_EOL;

            return 0;
        } catch (VersionDisplayException $e) {
            echo 'PhpCodeArcheology v'.$e->getMessage().PHP_EOL;

            return 0;
        }

        $formatter = new CliFormatter(
            $config->isNoColor() ? false : null
        );
        $output = new CliOutput();
        $output->setFormatter($formatter);

        $command = $config->getCommand();
        if (null !== $command) {
            return $this->dispatchCommand($command, $config, $output, $formatter);
        }

        $config->applyMemoryLimit();

        $bootstrap = $this->factory->createBootstrapService();
        $bootstrap->detectFrameworkAndCoverage($config);

        if (!$bootstrap->isBreakingChangesAcknowledged($config)) {
            if (!$bootstrap->promptBreakingChanges($config, $output, $formatter)) {
                return 0;
            }
        }

        $pipeline = $this->createPipeline();

        if ($config->isQuickMode()) {
            [$registry, $reader] = $pipeline->runQuickAnalysis($config, $output);
            (new QuickOutput($reader, $registry, $output, $formatter))->render();

            $frameworkResult = $config->getFrameworkDetection();
            if (null !== $frameworkResult
                && $frameworkResult->hasAnyFramework()) {
                $output->outNl('  Frameworks: '.$formatter->info($frameworkResult->getSummary()));
                $output->outNl();
            }

            return 0;
        }

        [$registry, $reader, $writer, $problems] = $pipeline->runAnalysis($config, $output);

        $this->factory->createReportOrchestrator()->generateReports($config, $reader, $writer, $registry, $output, $problems);

        return $this->determineExitCode($config, $problems);
    }

    /**
     * Run full analysis pipeline: parse → git → calculators → predictors → debt.
     *
     * @return array{MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface, array<int, int>}
     */
    public function runAnalysis(Config $config, CliOutput $output): array
    {
        return $this->createPipeline()->runAnalysis($config, $output);
    }

    private function createPipeline(): AnalysisPipeline
    {
        return $this->factory->createAnalysisPipeline();
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws MultipleConfigFilesException
     * @throws ConfigFileExtensionNotSupportedException
     * @throws HelpDisplayException
     * @throws VersionDisplayException
     */
    private function createConfig(array $argv): Config
    {
        try {
            $config = (new ArgumentParser())->parse($argv);
        } catch (ParamException $e) {
            echo PHP_EOL."Error: {$e->getMessage()}";
            exit(1);
        }

        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        if (!$config->get('reportType')) {
            $config->set('reportType', 'html');
        }

        $runningDir = $config->getRunningDir();
        $reportDirRaw = $config->getReportDir();
        if ('' === $reportDirRaw) {
            $reportDir = $runningDir.'/tmp/report';
        } elseif (!str_starts_with($reportDirRaw, DIRECTORY_SEPARATOR)) {
            $reportDir = $runningDir.DIRECTORY_SEPARATOR.$reportDirRaw;
        } else {
            $reportDir = $reportDirRaw;
        }
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $resolved = realpath($reportDir);
        $config->set('reportDir', false !== $resolved ? $resolved : $reportDir);

        if (!$config->get('packageSize')) {
            $config->set('packageSize', 2);
        }

        if (null === $config->getCommand()) {
            try {
                $config->validate();
            } catch (ConfigException $e) {
                echo PHP_EOL."Error: {$e->getMessage()}";
                exit(1);
            }
        }

        return $config;
    }

    private function dispatchCommand(string $command, Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        return match ($command) {
            'init' => (new Command\InitCommand())->execute($config, $output, $formatter),
            'compare' => (new Command\CompareCommand())->execute($config, $output, $formatter),
            'baseline' => (new Command\BaselineCommand($this))->execute($config, $output, $formatter),
            'mcp' => (new McpCommand($this))->execute($config, $output, $formatter),
            default => throw new ParamException("Unknown command: $command"),
        };
    }

    private function getHelpText(): string
    {
        $version = Version::CURRENT;

        return <<<HELP
            PhpCodeArcheology v{$version} — PHP static analysis for architecture & maintainability

            Usage: vendor/bin/phpcodearcheology [command] [options] [path...]

            Options:
              --help                 Show this help message
              --version              Show version number
              --quick                Quick terminal-only output (no report)
              --report-type=TYPE     Report type: html, json, markdown, sarif, graph, ai-summary
              --report-dir=DIR       Output directory for reports (default: tmp/report)
              --coverage-file=FILE   Path to Clover XML coverage file
              --git-root=DIR         Git repository root (for git metrics)
              --fail-on=LEVEL        Exit with error on: error, warning, info
              --extensions=EXT       File extensions to analyze (default: php)
              --exclude=PATHS        Comma-separated paths to exclude
              --no-color             Disable colored output
              --generate-claude-md   Generate CLAUDE.md file for AI assistants

            Commands:
              mcp                    Start MCP server for AI-native code intelligence
              init                   Create configuration file
              compare                Compare two analysis results
              baseline               Create problem baseline
            HELP;
    }

    /** @param array<int, int> $problems */
    private function determineExitCode(Config $config, array $problems): int
    {
        $failOn = $config->getFailOn();

        if (null === $failOn) {
            $qualityGateRaw = $config->get('qualityGate');
            if (is_array($qualityGateRaw)) {
                $maxErrors = $qualityGateRaw['maxErrors'] ?? null;
                $maxWarnings = $qualityGateRaw['maxWarnings'] ?? null;

                if (null !== $maxErrors && $problems[PredictionInterface::ERROR] > $maxErrors) {
                    return 1;
                }
                if (null !== $maxWarnings && $problems[PredictionInterface::WARNING] > $maxWarnings) {
                    return 1;
                }

                return 0;
            }

            return 0;
        }

        return match ($failOn) {
            'error' => $problems[PredictionInterface::ERROR] > 0 ? 1 : 0,
            'warning' => ($problems[PredictionInterface::ERROR] > 0 || $problems[PredictionInterface::WARNING] > 0) ? 1 : 0,
            default => 0,
        };
    }
}
