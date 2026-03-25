<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\ConfigFile;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigFile\Exceptions\ReportDirNotFoundException;

class ConfigFileParserJson implements ConfigFileParserInterface
{
    public function __construct(private string $file)
    {
    }

    public function parse(Config $config): void
    {

    }

    public function parseJson(string $json, Config $config): void
    {
        $data = json_decode($json, true);

        $config->set('configFileDir', dirname($this->file));

        if (isset($data['include'])) {
            $config->set('files', $data['include']);
        }

        if (isset($data['exclude'])) {
            $config->set('exclude', $data['exclude']);
        }

        if (isset($data['extensions'])) {
            $config->set('extensions', $data['extensions']);
        }

        if (isset($data['packageSize'])) {
            $config->set('packageSize', $data['packageSize']);
        }

        // CLI flags take precedence over config file
        if (isset($data['reportType']) && !$config->has('reportType')) {
            $config->set('reportType', $data['reportType']);
        }

        if (isset($data['reportDir'])) {
            $reportDir = $data['reportDir'];

            // Resolve relative paths against runningDir
            if (!str_starts_with($reportDir, DIRECTORY_SEPARATOR)) {
                $reportDir = $config->get('runningDir') . DIRECTORY_SEPARATOR . $reportDir;
            }

            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }

            // Use realpath only after the directory exists, with safe fallback
            $resolved = realpath($reportDir);
            $config->set('reportDir', $resolved !== false ? $resolved : $reportDir);
        }

        if (isset($data['git'])) {
            if (isset($data['git']['root'])) {
                $root = $data['git']['root'];
                $resolved = realpath($root);
                if ($resolved === false) {
                    // Try relative to runningDir
                    $resolved = realpath($config->get('runningDir') . DIRECTORY_SEPARATOR . $root);
                }
                if ($resolved !== false) {
                    $data['git']['root'] = $resolved;
                }
                // If still unresolved, keep original — GitAnalyzer will report the error
            }
            $config->set('git', $data['git']);
        }

        if (isset($data['qualityGate'])) {
            $config->set('qualityGate', $data['qualityGate']);
        }

        if (isset($data['thresholds'])) {
            $config->set('thresholds', $data['thresholds']);
        }

        if (isset($data['graph'])) {
            $config->set('graph', $data['graph']);
        }

        if (isset($data['php'])) {
            $config->set('php', $data['php']);
        }
    }
}
