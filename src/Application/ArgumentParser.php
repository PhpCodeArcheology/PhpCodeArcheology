<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

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

        // Detect subcommand (first non-flag argument)
        $commands = ['init', 'compare', 'baseline', 'mcp'];
        if (!empty($argv)) {
            $firstKey = array_key_first($argv);
            $firstArg = $argv[$firstKey];
            if (!str_starts_with($firstArg, '-') && in_array($firstArg, $commands, true)) {
                $config->set('command', $firstArg);
                unset($argv[$firstKey]);

                // Store remaining positional args as commandArgs (won't be overwritten by config file)
                $commandArgs = array_values(array_filter($argv, fn($v) => !str_starts_with($v, '-')));
                $config->set('commandArgs', $commandArgs);
            }
        }

        // Normalize space-separated value params: --param value → --param=value
        $valueParams = ['coverage-file', 'report-type', 'report-dir', 'git-root', 'fail-on', 'extensions', 'exclude'];
        $argv = array_values($argv);
        $normalized = [];
        $i = 0;
        while ($i < count($argv)) {
            if (
                preg_match('#^--([\w\-]+)$#', $argv[$i], $m)
                && in_array($m[1], $valueParams, true)
                && isset($argv[$i + 1])
                && !str_starts_with($argv[$i + 1], '-')
            ) {
                $normalized[] = $argv[$i] . '=' . $argv[$i + 1];
                $i += 2;
            } else {
                $normalized[] = $argv[$i];
                $i++;
            }
        }
        $argv = $normalized;

        foreach ($argv as $key => $value) {
            if (preg_match('#--([\w\-]+)=(.*)#', $value, $matches)) {
                [, $param, $value] = $matches;

                switch ($param) {
                    case 'extensions':
                    case 'exclude':
                        $config->set($param, explode(',', $value));
                        break;

                    case 'report-type':
                        $types = array_map('trim', explode(',', $value));
                        $config->set('reportType', count($types) === 1 ? $types[0] : $types);
                        break;

                    case 'report-dir':
                        $config->set('reportDir', $value);
                        break;

                    case 'git-root':
                        $resolved = realpath($value);
                        if ($resolved === false) {
                            throw new ParamException("Git root directory '$value' does not exist.");
                        }
                        $git = $config->get('git') ?? [];
                        $git['root'] = $resolved;
                        $config->set('git', $git);
                        break;

                    case 'coverage-file':
                        $resolved = realpath($value);
                        $config->set('coverageFile', $resolved !== false ? $resolved : $value);
                        break;

                    case 'fail-on':
                        $config->set('failOn', $value);
                        break;

                    default:
                        throw new ParamException('CLI parameter "' . $param . '" does not exist.');
                }

                unset($argv[$key]);
            }
            elseif (preg_match('#--([\w\-]+)#', $value, $matches)) {
                $param = $matches[1];

                switch ($param) {
                    case 'version':
                        echo PHP_EOL . "PhpCodeArcheology v" . Application::VERSION;
                        exit;

                    case 'generate-claude-md':
                        $config->set('generateClaudeMd', true);
                        break;

                    case 'no-color':
                        $config->set('noColor', true);
                        break;

                    case 'quick':
                        $config->set('quickMode', true);
                        break;

                    default:
                        throw new ParamException('CLI parameter "' . $param . '" does not exist.');
                }

                unset($argv[$key]);
            }
        }

        if (empty($argv)) {
            $config->set('files', [getcwd() . '/src']);
            return $config;
        }

        $config->set('files', $argv);

        return $config;
    }
}
