<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Command;

use PhpCodeArch\Application\AnalysisPipelineInterface;
use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\Version;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Predictions\PredictionInterface;

class BaselineCommand
{
    private const BASELINE_FILENAME = '.phpcodearch-baseline.json';

    public function __construct(
        private readonly AnalysisPipelineInterface $application,
    ) {
    }

    public function execute(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        $argsRaw = $config->get('commandArgs');
        $args = is_array($argsRaw) ? $argsRaw : [];
        $subCommand = is_string($args[0] ?? null) ? $args[0] : null;

        if (null === $subCommand || !in_array($subCommand, ['create', 'check'], true)) {
            $output->outNl($formatter->error('Usage: phpcodearcheology baseline <create|check> [path...]'));

            return 1;
        }

        // Remaining args after sub-subcommand become the analysis paths
        $analysisPaths = array_slice($args, 1);
        if ([] !== $analysisPaths) {
            $config->set('files', $analysisPaths);
        } elseif (!$config->has('files') || empty($config->get('files'))) {
            $config->set('files', [getcwd().'/src']);
        }

        // Validate paths before analysis
        try {
            $config->validate();
        } catch (\PhpCodeArch\Application\ConfigException $e) {
            $output->outNl($formatter->error($e->getMessage()));

            return 1;
        }

        $memoryLimitRaw = $config->get('memoryLimit');
        $memoryLimit = is_string($memoryLimitRaw) ? $memoryLimitRaw : '1G';
        ini_set('memory_limit', $memoryLimit);

        return match ($subCommand) {
            'create' => $this->create($config, $output, $formatter),
            'check' => $this->check($config, $output, $formatter),
        };
    }

    private function create(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        [$metricsController, $problems] = $this->application->runAnalysis($config, $output);

        $baseline = $this->buildBaseline($metricsController, $problems);
        $baselinePath = $this->getBaselinePath($config);

        $json = json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($baselinePath, $json);

        $totalProblems = count($baseline['problems']);
        $output->outNl();
        $output->outNl($formatter->success("Baseline created with $totalProblems problems."));
        $output->outNl($formatter->dim("Saved to: $baselinePath"));
        $output->outNl();

        return 0;
    }

    private function check(Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        $baselinePath = $this->getBaselinePath($config);

        if (!file_exists($baselinePath)) {
            $output->outNl($formatter->error('No baseline found. Run "phpcodearcheology baseline create" first.'));

            return 1;
        }

        $baselineContent = file_get_contents($baselinePath);
        if (false === $baselineContent) {
            $output->outNl($formatter->error('Could not read baseline file.'));

            return 1;
        }
        $baselineData = json_decode($baselineContent, true);
        if (null === $baselineData || !is_array($baselineData)) {
            $output->outNl($formatter->error('Invalid baseline file.'));

            return 1;
        }

        [$metricsController, $problems] = $this->application->runAnalysis($config, $output);

        $baselineProblems = is_array($baselineData['problems'] ?? null) ? $baselineData['problems'] : [];
        $baselineCreatedAt = is_string($baselineData['createdAt'] ?? null) ? $baselineData['createdAt'] : 'unknown';

        $currentProblems = $this->collectProblems($metricsController);
        $baselineSignatures = $this->buildSignatureMap($baselineProblems);

        $newProblems = [];
        foreach ($currentProblems as $problem) {
            $sig = $problem['entityId'].'|'.$problem['message'].'|'.$problem['level'];
            if (!isset($baselineSignatures[$sig])) {
                $newProblems[] = $problem;
            }
        }

        $resolvedCount = 0;
        $currentSignatures = $this->buildSignatureMap($currentProblems);
        foreach ($baselineProblems as $bp) {
            if (!is_array($bp)) {
                continue;
            }
            $sig = (is_string($bp['entityId'] ?? null) ? $bp['entityId'] : '').'|'.(is_string($bp['message'] ?? null) ? $bp['message'] : '').'|'.(is_string($bp['level'] ?? null) ? $bp['level'] : '');
            if (!isset($currentSignatures[$sig])) {
                ++$resolvedCount;
            }
        }

        // Output
        $output->outNl();
        $output->outNl($formatter->bold('Baseline Check'));
        $output->outNl($formatter->dim('Baseline: '.$baselineCreatedAt.
            ' ('.count($baselineProblems).' problems)'));
        $output->outNl($formatter->dim('Current: '.count($currentProblems).' problems'));

        if ($resolvedCount > 0) {
            $output->outNl($formatter->success("Resolved: $resolvedCount problems"));
        }

        $newErrors = 0;
        if ([] === $newProblems) {
            $output->outNl($formatter->success('No new problems detected.'));
        } else {
            $output->outNl($formatter->error(count($newProblems).' new problem(s):'));
            $output->outNl();

            foreach ($newProblems as $p) {
                $levelStr = strtoupper($p['level']);
                $color = match ($p['level']) {
                    'error' => $formatter->error(...),
                    'warning' => $formatter->warning(...),
                    default => $formatter->info(...),
                };

                $output->outNl('  '.$color("[$levelStr]").' '.$p['entityId']);
                $output->outNl('    '.$p['message']);

                if ('error' === $p['level']) {
                    ++$newErrors;
                }
            }
        }

        $output->outNl();

        return $newErrors > 0 ? 1 : 0;
    }

    /**
     * @param array<int, int> $problems
     *
     * @return array{createdAt: string, toolVersion: string, problemCounts: array{errors: int, warnings: int, info: int}, problems: list<array{entityId: string, category: string, level: string, message: string}>}
     */
    private function buildBaseline(MetricsController $metricsController, array $problems): array
    {
        return [
            'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'toolVersion' => Version::CURRENT,
            'problemCounts' => [
                'errors' => $problems[PredictionInterface::ERROR] ?? 0,
                'warnings' => $problems[PredictionInterface::WARNING] ?? 0,
                'info' => $problems[PredictionInterface::INFO] ?? 0,
            ],
            'problems' => $this->collectProblems($metricsController),
        ];
    }

    /**
     * @return list<array{entityId: string, category: string, level: string, message: string}>
     */
    private function collectProblems(MetricsController $metricsController): array
    {
        $problems = [];

        foreach ($metricsController->getAllCollections() as $collection) {
            $category = match (true) {
                $collection instanceof FileMetricsCollection => 'file',
                $collection instanceof ClassMetricsCollection => 'class',
                $collection instanceof FunctionMetricsCollection => 'function',
                default => null,
            };

            if (null === $category) {
                continue;
            }

            $entityId = (string) $collection->getIdentifier();

            foreach ($collection->getAll() as $metricValue) {
                if (!$metricValue->hasProblems()) {
                    continue;
                }

                foreach ($metricValue->getProblems() as $problem) {
                    $problems[] = [
                        'entityId' => $entityId,
                        'category' => $category,
                        'level' => match ($problem->getProblemLevel()) {
                            PredictionInterface::ERROR => 'error',
                            PredictionInterface::WARNING => 'warning',
                            PredictionInterface::INFO => 'info',
                            default => 'unknown',
                        },
                        'message' => $problem->getMessage(),
                    ];
                }
            }
        }

        return $problems;
    }

    /**
     * @param array<mixed> $problems
     *
     * @return array<string, true>
     */
    private function buildSignatureMap(array $problems): array
    {
        $map = [];
        foreach ($problems as $p) {
            if (!is_array($p)) {
                continue;
            }
            $entityId = is_string($p['entityId'] ?? null) ? $p['entityId'] : '';
            $message = is_string($p['message'] ?? null) ? $p['message'] : '';
            $level = is_string($p['level'] ?? null) ? $p['level'] : '';
            $sig = $entityId.'|'.$message.'|'.$level;
            $map[$sig] = true;
        }

        return $map;
    }

    private function getBaselinePath(Config $config): string
    {
        $reportDir = $config->get('reportDir');
        if (is_string($reportDir) && '' !== $reportDir) {
            return rtrim($reportDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::BASELINE_FILENAME;
        }

        $runningDir = $config->get('runningDir');
        $base = is_string($runningDir) && '' !== $runningDir ? $runningDir : (getcwd() ?: '');

        return $base.DIRECTORY_SEPARATOR.self::BASELINE_FILENAME;
    }
}
