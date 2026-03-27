<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\OutputInterface;
use PhpCodeArch\Application\Version;
use PhpParser\ParserFactory;

final class BootstrapService
{
    public function detectFrameworkAndCoverage(Config $config): void
    {
        $frameworkConfig = $config->get('framework');
        $frameworkDetect = !is_array($frameworkConfig) || ($frameworkConfig['detect'] ?? true);
        if (!$frameworkDetect) {
            return;
        }

        $detector = new FrameworkDetector();
        $runningDirRaw = $config->get('runningDir');
        $filesRaw = $config->get('files');
        $projectRoot = is_string($runningDirRaw) && '' !== $runningDirRaw
            ? $runningDirRaw
            : (is_array($filesRaw) && is_string($filesRaw[0] ?? null) ? $filesRaw[0] : (getcwd() ?: ''));
        $frameworkResult = $detector->detect($projectRoot);
        $config->set('frameworkDetection', $frameworkResult);

        $testScanner = new TestDirectoryScanner($frameworkResult);
        $testScanResult = $testScanner->scan($projectRoot);
        $config->set('testScanResult', $testScanResult);

        if ([] !== $testScanResult->classBasedTestFiles) {
            $phpParser = (new ParserFactory())->createForHostVersion();
            $coversParser = new TestCoversParser($phpParser);
            $coversResult = $coversParser->parse($testScanResult->classBasedTestFiles);
            $config->set('coversParseResult', $coversResult);
        }

        // Use composer.json directory as the true project root for coverage
        $composerRoot = '' !== $frameworkResult->composerJsonPath
            ? dirname($frameworkResult->composerJsonPath)
            : $projectRoot;

        // Auto-detect Clover XML in common locations
        if (null === $config->get('coverageFile')) {
            $candidates = ['clover.xml', 'coverage/clover.xml', 'build/logs/clover.xml', 'build/coverage/clover.xml'];
            foreach ($candidates as $candidate) {
                $path = $composerRoot.DIRECTORY_SEPARATOR.$candidate;
                if (is_file($path)) {
                    $config->set('coverageFile', $path);
                    break;
                }
            }
        }

        $coverageFile = $config->get('coverageFile');
        if (is_string($coverageFile)) {
            if (is_file($coverageFile)) {
                $cloverParser = new CloverXmlParser();
                $coverageData = $cloverParser->parse($coverageFile, $composerRoot);
                $config->set('coverageData', $coverageData);
            } else {
                fwrite(STDERR, "Warning: Coverage file not found: {$coverageFile}\n");
            }
        }
    }

    public function isBreakingChangesAcknowledged(Config $config): bool
    {
        $acknowledged = $config->get('acknowledgedVersion');

        return is_string($acknowledged)
            && version_compare($acknowledged, Version::BREAKING_CHANGES, '>=');
    }

    public function acknowledgeBreakingChanges(Config $config): void
    {
        $runningDir = $config->get('runningDir');
        $runningDir = is_string($runningDir) ? $runningDir : (getcwd() ?: '');

        // Find existing config file or create a new YAML one
        $yamlPath = $runningDir.DIRECTORY_SEPARATOR.'php-codearch-config.yaml';
        $jsonPath = $runningDir.DIRECTORY_SEPARATOR.'.phpcodearch.json';

        if (is_file($jsonPath)) {
            $content = file_get_contents($jsonPath);
            $data = false !== $content ? json_decode($content, true) : [];
            if (!is_array($data)) {
                $data = [];
            }
            $data['acknowledgedVersion'] = Version::BREAKING_CHANGES;
            file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } else {
            // Use YAML config (create or update)
            if (is_file($yamlPath)) {
                $content = file_get_contents($yamlPath);
                $yaml = false !== $content ? $content : '';
            } else {
                $yaml = '';
            }

            // Append or replace acknowledgedVersion
            if (str_contains($yaml, 'acknowledgedVersion:')) {
                $yaml = (string) preg_replace(
                    '/acknowledgedVersion:.*/',
                    'acknowledgedVersion: '.Version::BREAKING_CHANGES,
                    $yaml
                );
            } else {
                $yaml = rtrim($yaml)."\nacknowledgedVersion: ".Version::BREAKING_CHANGES."\n";
            }

            file_put_contents($yamlPath, $yaml);
        }

        $config->set('acknowledgedVersion', Version::BREAKING_CHANGES);
    }

    /**
     * Show the breaking-changes notice and prompt the user.
     *
     * Returns true if the user confirmed and the acknowledgement was written,
     * false if the user aborted.
     */
    public function promptBreakingChanges(Config $config, OutputInterface $output, CliFormatter $formatter): bool
    {
        $output->outNl($formatter->warning('Important: Metric calculations have changed in v'.Version::BREAKING_CHANGES.'.'));
        $output->outNl('Several formulas have been corrected (Halstead, Coupling, LCOM, CC, and others).');
        $output->outNl('Analysis results may differ from previous runs.');
        $output->outNl();
        $output->outNl($formatter->dim('Recommendation: Back up your existing reports before continuing.'));
        $output->outNl($formatter->dim('See docs/metrics-formulas.md for details on the updated calculations.'));
        $output->outNl();
        $answer = $output->prompt('Continue with analysis? (y/N)', 'N');
        if ('y' !== strtolower($answer)) {
            $output->outNl('Aborted. Your existing reports remain unchanged.');

            return false;
        }
        $this->acknowledgeBreakingChanges($config);

        return true;
    }
}
