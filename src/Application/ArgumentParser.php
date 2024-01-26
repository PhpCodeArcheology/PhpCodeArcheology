<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\Exceptions\ReportDirNotFoundException;

final class ArgumentParser
{
    public function parse(array $argv): Config
    {
        $config = new Config();

        if (count($argv) === 0) {
            return $config;
        }

        if (str_ends_with($argv[0], 'phpcodearcheology')) {
            array_shift($argv);
        }

        foreach ($argv as $key => $value) {
            if (preg_match('#--([\w\-]+)=(.*)#', $value, $matches)) {
                [, $param, $value] = $matches;

                switch ($param) {
                    case 'extensions':
                    case 'exclude':
                        $config->set($param, explode(',', $value));
                        break;

                    case 'report-type':
                        $config->set('reportType', $value);
                        break;

                    case 'report-dir':
                        $config->set('reportDir', realpath($value));
                        break;
                }

                unset($argv[$key]);
            }
        }

        if (empty($argv)) {
            $config->set('files', [getcwd()]);
            return $config;
        }

        $config->set('files', $argv);

        return $config;
    }
}
