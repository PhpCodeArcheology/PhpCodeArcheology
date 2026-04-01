<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

final class ArgumentParser
{
    /** @param array<int, string> $argv */
    public function parse(array $argv): Config
    {
        $config = new Config();

        if (0 === count($argv)) {
            return $config;
        }

        if (str_ends_with((string) $argv[0], 'phpcodearcheology')) {
            array_shift($argv);
        }

        // Detect subcommand (first non-flag argument)
        $commands = ['init', 'compare', 'baseline', 'mcp'];
        if ([] !== $argv) {
            $firstKey = array_key_first($argv);
            $firstArg = $argv[$firstKey];
            if (!str_starts_with((string) $firstArg, '-') && in_array($firstArg, $commands, true)) {
                $config->set('command', $firstArg);
                unset($argv[$firstKey]);

                // Store remaining positional args as commandArgs (won't be overwritten by config file)
                $commandArgs = array_values(array_filter($argv, fn ($v): bool => !str_starts_with((string) $v, '-')));
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
                preg_match('#^--([\w\-]+)$#', (string) $argv[$i], $m)
                && in_array($m[1], $valueParams, true)
                && isset($argv[$i + 1])
                && !str_starts_with($argv[$i + 1], '-')
            ) {
                $normalized[] = $argv[$i].'='.$argv[$i + 1];
                $i += 2;
            } else {
                $normalized[] = $argv[$i];
                ++$i;
            }
        }
        $argv = $normalized;

        foreach ($argv as $key => $value) {
            if (preg_match('#--([\w\-]+)=(.*)#', (string) $value, $matches)) {
                [, $param, $value] = $matches;

                switch ($param) {
                    case 'extensions':
                    case 'exclude':
                        $config->set($param, explode(',', $value));
                        break;

                    case 'report-type':
                        $types = array_map(trim(...), explode(',', $value));
                        $config->set('reportType', 1 === count($types) ? $types[0] : $types);
                        break;

                    case 'report-dir':
                        $config->set('reportDir', $value);
                        break;

                    case 'git-root':
                        $resolved = realpath($value);
                        if (false === $resolved) {
                            throw new ParamException("Git root directory '$value' does not exist.");
                        }
                        $existing = $config->get('git');
                        $git = is_array($existing) ? $existing : [];
                        $git['root'] = $resolved;
                        $config->set('git', $git);
                        break;

                    case 'coverage-file':
                        $resolved = realpath($value);
                        $config->set('coverageFile', false !== $resolved ? $resolved : $value);
                        break;

                    case 'fail-on':
                        $config->set('failOn', $value);
                        break;

                    default:
                        throw new ParamException('CLI parameter "'.$param.'" does not exist.');
                }

                unset($argv[$key]);
            } elseif (preg_match('#--([\w\-]+)#', (string) $value, $matches)) {
                $param = $matches[1];

                switch ($param) {
                    case 'help':
                        throw new HelpDisplayException();
                    case 'version':
                        throw new VersionDisplayException(Application::VERSION);
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
                        throw new ParamException('CLI parameter "'.$param.'" does not exist.');
                }

                unset($argv[$key]);
            }
        }

        if ([] === $argv) {
            $config->set('files', [getcwd().'/src']);

            return $config;
        }

        $config->set('files', $argv);

        return $config;
    }
}
