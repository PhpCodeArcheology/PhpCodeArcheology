<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\ConfigFile;

use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use Symfony\Component\Yaml\Yaml;

class ConfigFileParserFactory
{
    /**
     * @throws ConfigFileExtensionNotSupportedException
     */
    public static function createFromFile(string $file): ConfigFileParserInterface
    {
        switch (pathinfo($file, PATHINFO_EXTENSION)) {
            case 'yaml':
                $yaml = new Yaml();
                return new ConfigFileParserYaml($file, $yaml);
            default:
                throw new ConfigFileExtensionNotSupportedException("The file $file has the wrong format.");
        }
    }
}
