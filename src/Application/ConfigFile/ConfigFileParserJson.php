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

        if (isset($data['reportType'])) {
            $config->set('reportType', $data['reportType']);
        }

        if (isset($data['reportDir'])) {
            $reportDir = realpath($data['reportDir']);

            if (! $reportDir) {
                $trimmedPath = trim(trim($data['reportDir']), DIRECTORY_SEPARATOR);
                $reportDir = realpath($config->get('runningDir')) . DIRECTORY_SEPARATOR . $trimmedPath;
                mkdir($reportDir, recursive: true);
            }

            $config->set('reportDir', realpath($reportDir));
        }
    }
}
