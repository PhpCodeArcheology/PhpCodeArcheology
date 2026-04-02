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

        $basePath = rtrim(is_string($path) ? $path : '', DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $candidates = [
            $basePath.'php-codearch-config.yaml',
            $basePath.'php-codearch-config.yaml.dist',
        ];

        $configFile = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $configFile = $candidate;
                break;
            }
        }

        if (null === $configFile) {
            return false;
        }

        $this->loadFile($configFile);

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
