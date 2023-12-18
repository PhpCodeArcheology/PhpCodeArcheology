<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

final class Application
{
    public function run(array $argv): void
    {
        $config = (new ArgumentParser())->parse($argv);

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo "Fehler: {$e->getMessage()}";
        }

        $fileList = new FileList();
        $fileList->fetch($config);

        var_dump($fileList->getFiles());
    }
}
