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
        $filesRaw = $this->config->get('files');
        if (!is_array($filesRaw) || 1 !== count($filesRaw)) {
            return false;
        }

        $path = $this->config->get('runningDir');

        $configFile = sprintf(
            '%s%s%s.*',
            rtrim(is_string($path) ? $path : '', DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            'php-codearch-config'
        );

        $foundFiles = glob($configFile);

        if (false === $foundFiles) {
            return false;
        }

        if (count($foundFiles) > 1) {
            throw new MultipleConfigFilesException('Multiple config files detected.');
        }

        if (0 === count($foundFiles)) {
            return false;
        }

        $this->loadFile($foundFiles[0]);

        return true;
    }

    public function checkFile(): void
    {
    }

    /**
     * @throws ConfigFileExtensionNotSupportedException
     */
    public function loadFile(string $file): void
    {
        $parser = ConfigFileParserFactory::createFromFile($file);
        $parser->parse($this->config);
    }
}
