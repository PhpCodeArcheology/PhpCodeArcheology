<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

final class ArgumentParser
{
    public function parse(array $argv): Config
    {
        $config = new Config();

        if (count($argv) === 0) {
            return $config;
        }

        if (str_ends_with($argv[0], 'php-legacy-analyzer')) {
            array_shift($argv);
        }

        foreach ($argv as $key => $value) {
            if (preg_match('#\-\-([\w\-]+)=(.*)#', $value, $matches)) {
                [, $param, $value] = $matches;

                switch ($param) {
                    case 'exclude':
                        $config->set($param, explode(',', $value));
                        break;

                    case 'report-type':
                        $config->set('reportType', $value);
                        break;
                }

                unset($argv[$key]);
            }
        }

        $config->set('files', $argv);

        return $config;
    }
}
