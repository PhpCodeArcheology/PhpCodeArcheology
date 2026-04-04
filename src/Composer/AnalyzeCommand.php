<?php

declare(strict_types=1);

namespace PhpCodeArch\Composer;

use Composer\Command\BaseCommand;
use PhpCodeArch\Application\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends BaseCommand
{
    private const CONFIG_FILES = [
        'php-codearch-config.yaml',
        'php-codearch-config.yaml.dist',
        '.phpcodearch.json',
    ];

    protected function configure(): void
    {
        $this
            ->setName('codearch:analyze')
            ->setDescription('Run PhpCodeArcheology static analysis')
            ->addArgument('path', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Source directories to analyze')
            ->addOption('quick', null, InputOption::VALUE_NONE, 'Quick terminal-only output (no report)')
            ->addOption('report-type', null, InputOption::VALUE_REQUIRED, 'Report type: html, json, markdown, sarif, graph, ai-summary')
            ->addOption('report-dir', null, InputOption::VALUE_REQUIRED, 'Output directory for reports')
            ->addOption('coverage-file', null, InputOption::VALUE_REQUIRED, 'Path to Clover XML coverage file')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Exit with error on: error, warning, info')
            ->addOption('extensions', null, InputOption::VALUE_REQUIRED, 'File extensions to analyze (comma-separated)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Paths to exclude (comma-separated)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argv = $this->buildArgv($input);

        return (new Application())->run($argv);
    }

    /**
     * Build an argv array compatible with Application::run().
     *
     * @return list<string>
     */
    public function buildArgv(InputInterface $input): array
    {
        $argv = [];

        /** @var list<string> $paths */
        $paths = $input->getArgument('path');

        if ([] === $paths && !$this->hasConfigFile()) {
            $paths = $this->detectSourceDirs();
        }

        if ($input->getOption('quick')) {
            $argv[] = '--quick';
        }

        $stringOptions = ['report-type', 'report-dir', 'coverage-file', 'fail-on', 'extensions', 'exclude'];
        foreach ($stringOptions as $option) {
            $value = $input->getOption($option);
            if (is_string($value) && '' !== $value) {
                $argv[] = '--'.$option.'='.$value;
            }
        }

        return [...$argv, ...$paths];
    }

    /**
     * Extract PSR-4 source directories from the root package's composer.json.
     *
     * @return list<string>
     */
    public function detectSourceDirs(): array
    {
        try {
            $autoload = $this->requireComposer()->getPackage()->getAutoload();
        } catch (\RuntimeException) {
            return [];
        }

        $dirs = [];

        foreach ($autoload['psr-4'] ?? [] as $paths) {
            foreach ((array) $paths as $path) {
                $path = rtrim((string) $path, '/');
                if ('' !== $path && !in_array($path, $dirs, true)) {
                    $dirs[] = $path;
                }
            }
        }

        return $dirs;
    }

    private function hasConfigFile(): bool
    {
        $cwd = getcwd();
        if (false === $cwd) {
            return false;
        }

        foreach (self::CONFIG_FILES as $file) {
            if (file_exists($cwd.DIRECTORY_SEPARATOR.$file)) {
                return true;
            }
        }

        return false;
    }
}
