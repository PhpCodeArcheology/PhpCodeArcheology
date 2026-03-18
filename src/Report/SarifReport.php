<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Application;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\ProblemInterface as ProblemModel;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class SarifReport implements ReportInterface
{
    private string $outputDir;

    public function __construct(
        private readonly Config              $config,
        private readonly DataProviderFactory $dataProviderFactory,
        private readonly false|\DateTimeImmutable $historyDate,
        protected readonly FilesystemLoader  $twigLoader,
        protected readonly Environment       $twig,
        private readonly CliOutput           $output)
    {
        $this->outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;

        if (!is_dir($this->outputDir)) {
            mkdir(directory: $this->outputDir, recursive: true);
        }
    }

    public function generate(): void
    {
        $this->output->outWithMemory('Generating SARIF report...');

        $problemData = $this->dataProviderFactory->getProblemDataProvider()->getTemplateData();

        $results = [];
        $rules = [];
        $ruleIndex = [];

        $categoryMap = [
            'fileProblems' => 'file',
            'classProblems' => 'class',
            'functionProblems' => 'function',
        ];

        foreach ($categoryMap as $key => $category) {
            foreach ($problemData[$key] ?? [] as $entityId => $entityProblems) {
                $data = $entityProblems['data'] ?? null;
                $filePath = '';
                $line = 1;

                if ($data instanceof FileMetricsCollection) {
                    $filePath = $data->getName();
                } elseif (method_exists($data, 'getPath')) {
                    $filePath = $data->getPath();
                }

                if (method_exists($data, 'get')) {
                    $startLine = $data->get('startLine');
                    if ($startLine !== null) {
                        $line = (int) $startLine->getValue();
                    }
                }

                foreach ($entityProblems['problems'] ?? [] as $problem) {
                    $ruleId = $this->generateRuleId($problem, $category);

                    if (!isset($ruleIndex[$ruleId])) {
                        $ruleIndex[$ruleId] = count($rules);
                        $rules[] = [
                            'id' => $ruleId,
                            'shortDescription' => [
                                'text' => $problem->getMessage(),
                            ],
                            'defaultConfiguration' => [
                                'level' => $this->mapLevel($problem->getProblemLevel()),
                            ],
                        ];

                        $recommendation = $problem->getRecommendation();
                        if ($recommendation !== '') {
                            $rules[count($rules) - 1]['help'] = [
                                'text' => $recommendation,
                            ];
                        }
                    }

                    $result = [
                        'ruleId' => $ruleId,
                        'ruleIndex' => $ruleIndex[$ruleId],
                        'level' => $this->mapLevel($problem->getProblemLevel()),
                        'message' => [
                            'text' => $problem->getMessage(),
                        ],
                    ];

                    if ($filePath !== '') {
                        $result['locations'] = [
                            [
                                'physicalLocation' => [
                                    'artifactLocation' => [
                                        'uri' => $filePath,
                                        'uriBaseId' => '%SRCROOT%',
                                    ],
                                    'region' => [
                                        'startLine' => max(1, $line),
                                    ],
                                ],
                            ],
                        ];
                    }

                    $results[] = $result;
                }
            }
        }

        $sarif = [
            '$schema' => 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/main/sarif-2.1/schema/sarif-schema-2.1.0.json',
            'version' => '2.1.0',
            'runs' => [
                [
                    'tool' => [
                        'driver' => [
                            'name' => 'PhpCodeArcheology',
                            'version' => Application::VERSION,
                            'informationUri' => 'https://github.com/PhpCodeArcheology/PhpCodeArcheology',
                            'rules' => $rules,
                        ],
                    ],
                    'results' => $results,
                ],
            ],
        ];

        $json = json_encode($sarif, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->outputDir . 'report.sarif.json', $json);

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl($formatter->success('SARIF report written to report.sarif.json'));
        $this->output->outNl();
    }

    private function mapLevel(int $level): string
    {
        return match ($level) {
            PredictionInterface::ERROR => 'error',
            PredictionInterface::WARNING => 'warning',
            PredictionInterface::INFO => 'note',
            default => 'none',
        };
    }

    private function generateRuleId(ProblemModel $problem, string $category): string
    {
        $message = $problem->getMessage();

        // Extract a stable rule ID from the message pattern
        $ruleId = preg_replace('/[^a-zA-Z0-9]/', '-', $message);
        $ruleId = preg_replace('/-+/', '-', $ruleId);
        $ruleId = trim($ruleId, '-');

        // Keep it short but unique
        if (strlen($ruleId) > 60) {
            $ruleId = substr($ruleId, 0, 60);
        }

        return 'PCA/' . $category . '/' . $ruleId;
    }
}
