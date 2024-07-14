<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\ConfigFile;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;

readonly class ConfigFileFinder
{
    public function __construct(private Config $config)
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

        $path = $this->config->get('runningDir');

        $configFile = sprintf(
            '%s%s%s.*',
            rtrim($path, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            'php-codearch-config'
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
