<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class ProblemsTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getProblems(string $severity = '', string $type = '', int $limit = 50): string
    {
        try {
            $data = $this->factory->getProblemDataProvider()->getTemplateData();

            $severityLevel = match (strtolower($severity)) {
                'error' => PredictionInterface::ERROR,
                'warning' => PredictionInterface::WARNING,
                'info' => PredictionInterface::INFO,
                default => null,
            };

            $problems = [];

            foreach (['files' => 'fileProblems', 'classes' => 'classProblems', 'functions' => 'functionProblems'] as $category => $key) {
                $problemSet = $data[$key] ?? [];
                if (!is_array($problemSet)) {
                    continue;
                }
                foreach ($problemSet as $id => $entry) {
                    if (!is_array($entry) || !isset($entry['problems']) || !is_array($entry['problems'])) {
                        continue;
                    }
                    foreach ($entry['problems'] as $problem) {
                        if (!$problem instanceof ProblemInterface) {
                            continue;
                        }
                        $level = $problem->getProblemLevel();

                        if (null !== $severityLevel && $level !== $severityLevel) {
                            continue;
                        }

                        $message = $problem->getMessage();

                        if ('' !== $type && false === stripos($message, $type)) {
                            continue;
                        }

                        $problems[] = [
                            'category' => $category,
                            'id' => (string) $id,
                            'level' => match ($level) {
                                PredictionInterface::ERROR => 'error',
                                PredictionInterface::WARNING => 'warning',
                                PredictionInterface::INFO => 'info',
                                default => 'unknown',
                            },
                            'message' => $message,
                        ];
                    }
                }
            }

            $total = count($problems);
            $problems = array_slice($problems, 0, $limit);

            $lines = ["# Problems ({$total} total, showing ".count($problems).')', ''];

            foreach ($problems as $p) {
                $lines[] = "[{$p['level']}] [{$p['category']}] {$p['id']}";
                $lines[] = "  {$p['message']}";
                $lines[] = '';
            }

            if (0 === $total) {
                $lines[] = 'No problems found.';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving problems.';
        }
    }
}
