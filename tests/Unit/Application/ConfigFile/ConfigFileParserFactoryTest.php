<?php

declare(strict_types=1);

use PhpCodeArch\Application\ConfigFile\ConfigFileParserFactory;
use PhpCodeArch\Application\ConfigFile\ConfigFileParserYaml;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;

it('creates yaml parser for .yaml files', function () {
    $parser = ConfigFileParserFactory::createFromFile('/some/path/php-codearch-config.yaml');
    expect($parser)->toBeInstanceOf(ConfigFileParserYaml::class);
});

it('creates yaml parser for .yaml.dist files', function () {
    $parser = ConfigFileParserFactory::createFromFile('/some/path/php-codearch-config.yaml.dist');
    expect($parser)->toBeInstanceOf(ConfigFileParserYaml::class);
});

it('throws exception for unsupported extensions', function () {
    ConfigFileParserFactory::createFromFile('/some/path/config.xml');
})->throws(ConfigFileExtensionNotSupportedException::class);

it('throws exception for plain .dist files without .yaml prefix', function () {
    ConfigFileParserFactory::createFromFile('/some/path/config.dist');
})->throws(ConfigFileExtensionNotSupportedException::class);
