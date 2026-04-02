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
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ('dist' === $extension && str_ends_with($file, '.yaml.dist')) {
            $extension = 'yaml';
        }

        switch ($extension) {
            case 'yaml':
                $yaml = new Yaml();

                return new ConfigFileParserYaml($file, $yaml);
            default:
                throw new ConfigFileExtensionNotSupportedException("The file $file has the wrong format.");
        }
    }
}
