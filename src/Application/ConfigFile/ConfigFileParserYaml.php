<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\ConfigFile;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileNotFoundException;
use Symfony\Component\Yaml\Yaml;

class ConfigFileParserYaml extends ConfigFileParserJson implements ConfigFileParserInterface
{
    public function __construct(
        private readonly string $file,
        private readonly Yaml $yaml
    )
    {
        parent::__construct($file);
    }

    /**
     * @throws ConfigFileNotFoundException
     */
    public function parse(Config $config): void
    {
        if (!is_file($this->file)) {
            throw new ConfigFileNotFoundException();
        }
        
        $parsedData = json_encode($this->yaml::parse(file_get_contents($this->file)));
        $this->parseJson($parsedData, $config);
    }
}
