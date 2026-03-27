<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Application\Service\BootstrapService;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Mcp\Command\McpCommand;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Report\ReportOrchestrator;
use PhpCodeArch\Report\ReportTypeNotSupported;

final readonly class Application implements AnalysisPipelineInterface
{
    /** @deprecated Use Version::CURRENT instead */
    public const VERSION = Version::CURRENT;

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
        } catch (VersionDisplayException $e) {
            echo PHP_EOL.'PhpCodeArcheology v'.$e->getMessage();

            return 0;
        }

        $formatter = new CliFormatter(
            $config->get('noColor') ? false : null
        );
        $output = new CliOutput();
        $output->setFormatter($formatter);

        $command = $config->get('command');
        if (is_string($command)) {
            return $this->dispatchCommand($command, $config, $output, $formatter);
        }

        $memoryLimit = $config->get('memoryLimit') ?? '1G';
        if (is_string($memoryLimit) && preg_match('/^[0-9]+[KMG]?$/i', $memoryLimit)) {
            ini_set('memory_limit', $memoryLimit);
        }

        $bootstrap = new BootstrapService();
        $bootstrap->detectFrameworkAndCoverage($config);

        if (!$bootstrap->isBreakingChangesAcknowledged($config)) {
            if (!$bootstrap->promptBreakingChanges($config, $output, $formatter)) {
                return 0;
            }
        }

        $pipeline = $this->createPipeline();

        if ($config->get('quickMode')) {
            $metricsController = $pipeline->runQuickAnalysis($config, $output);
            (new QuickOutput($metricsController, $output, $formatter))->render();

            $frameworkResult = $config->get('frameworkDetection');
            if ($frameworkResult instanceof FrameworkDetectionResult
                && $frameworkResult->hasAnyFramework()) {
                $output->outNl('  Frameworks: '.$formatter->info($frameworkResult->getSummary()));
                $output->outNl();
            }

            return 0;
        }

        [$metricsController, $problems] = $pipeline->runAnalysis($config, $output);

        (new ReportOrchestrator())->generateReports($config, $metricsController, $output, $problems);

        return $this->determineExitCode($config, $problems);
    }

    /**
     * Run full analysis pipeline: parse → git → calculators → predictors → debt.
     *
     * @return array{MetricsController, array<int, int>} [$metricsController, $problems]
     */
    public function runAnalysis(Config $config, CliOutput $output): array
    {
        return $this->createPipeline()->runAnalysis($config, $output);
    }

    private function createPipeline(): AnalysisPipeline
    {
        return new AnalysisPipeline();
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws MultipleConfigFilesException
     * @throws ConfigFileExtensionNotSupportedException
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

        $runningDirVal = $config->get('runningDir');
        $runningDir = is_string($runningDirVal) ? $runningDirVal : '';
        $reportDirRaw = $config->get('reportDir');
        if (!$reportDirRaw) {
            $reportDir = $runningDir.'/tmp/report';
        } elseif (is_string($reportDirRaw) && !str_starts_with($reportDirRaw, DIRECTORY_SEPARATOR)) {
            $reportDir = $runningDir.DIRECTORY_SEPARATOR.$reportDirRaw;
        } else {
            $reportDir = is_string($reportDirRaw) ? $reportDirRaw : '';
        }
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $resolved = realpath($reportDir);
        $config->set('reportDir', false !== $resolved ? $resolved : $reportDir);

        if (!$config->get('packageSize')) {
            $config->set('packageSize', 2);
        }

        if (!$config->get('command')) {
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

    /** @param array<int, int> $problems */
    private function determineExitCode(Config $config, array $problems): int
    {
        $failOnRaw = $config->get('failOn');
        $failOn = is_string($failOnRaw) ? $failOnRaw : null;

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
