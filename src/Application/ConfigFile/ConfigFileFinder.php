<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application\ConfigFile;

use Marcus\PhpLegacyAnalyzer\Application\Config;

class ConfigFileFinder
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     */
    public function checkRunningDir(): bool
    {
        if (count($this->config->get('files')) !== 1) {
            return false;
        }

        $files = $this->config->get('files');
        $path = reset($files);

        $configFile = sprintf(
            '%s%s%s.*',
            rtrim($path, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            'php-legarch-config'
        );

        $foundFiles = glob($configFile);

        if (count($foundFiles) > 1) {
            throw new MultipleConfigFilesException('Multiple config files detected.');
        }

        if (count($foundFiles) === 0) {
            return false;
        }

        $this->loadFile($foundFiles[0]);
        return true;
    }

    public function checkFile()
    {}

    /**
     * @throws ConfigFileExtensionNotSupportedException
     */
    public function loadFile(string $file): void
    {
        $parser = ConfigFileParserFactory::createFromFile($file);
        $parser->parse($this->config);
    }
}
