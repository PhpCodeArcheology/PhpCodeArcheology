<?php

declare(strict_types=1);

namespace PhpCodeArch\Git;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class GitAnalyzer
{
    private GitLogParser $parser;
    private string $since;

    public function __construct(
        private readonly Config $config,
        private readonly MetricsController $metricsController,
        private readonly CliOutput $output,
    ) {
        $gitConfig = $this->config->get('git') ?? [];
        $this->since = $gitConfig['since'] ?? '6 months ago';

        $gitRoot = $gitConfig['root'] ?? null;
        if ($gitRoot !== null) {
            $resolved = realpath($gitRoot);
            if ($resolved === false) {
                throw new \RuntimeException("Git root directory '$gitRoot' does not exist.");
            }
            $projectRoot = $resolved;
        } else {
            $projectRoot = $this->config->get('runningDir') ?? getcwd();
        }

        $this->parser = new GitLogParser($projectRoot);
    }

    public function analyze(): void
    {
        if (!$this->parser->isGitRepository()) {
            $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
            $this->output->outNl($formatter->warning('No Git repository detected — skipping Git analysis.'));
            $this->setDefaults();
            return;
        }

        $this->output->cls();
        $this->output->outWithMemory('Running Git analysis...');

        $changes = $this->parser->getFileChanges($this->since);

        // Project-level metrics
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'gitTotalCommits' => $changes['totalCommits'],
                'gitActiveAuthors' => count($changes['authors']),
                'gitAnalysisPeriod' => $this->since,
            ]
        );

        // File-level metrics
        $analyzedFiles = [];
        foreach ($this->config->get('files') as $dir) {
            // Collect all files that were analyzed
        }

        $now = time();

        foreach ($this->metricsController->getAllCollections() as $collection) {
            if (!$collection instanceof \PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection) {
                continue;
            }

            $filePath = $collection->get('filePath')?->getValue();
            if ($filePath === null) {
                continue;
            }

            $fileData = $changes['files'][$filePath] ?? null;

            if ($fileData !== null) {
                $authors = array_keys($fileData['authors']);
                $lastModified = $fileData['lastModified'];
                $ageDays = $lastModified > 0 ? (int) round(($now - $lastModified) / 86400) : 0;

                $this->metricsController->setMetricValuesByIdentifierString(
                    (string) $collection->getIdentifier(),
                    [
                        'gitChurnCount' => $fileData['commits'],
                        'gitLastModified' => $lastModified > 0 ? date('Y-m-d', $lastModified) : '',
                        'gitCodeAgeDays' => $ageDays,
                        'gitAuthorCount' => count($authors),
                        'gitAuthors' => $authors,
                    ]
                );
            } else {
                // File wasn't changed in the timeframe — get last modified from git
                $lastModified = $this->parser->getFileLastModified($filePath);
                $ageDays = $lastModified !== null ? (int) round(($now - $lastModified) / 86400) : 0;

                $this->metricsController->setMetricValuesByIdentifierString(
                    (string) $collection->getIdentifier(),
                    [
                        'gitChurnCount' => 0,
                        'gitLastModified' => $lastModified !== null ? date('Y-m-d', $lastModified) : '',
                        'gitCodeAgeDays' => $ageDays,
                        'gitAuthorCount' => 0,
                        'gitAuthors' => [],
                    ]
                );
            }
        }

        $formatter = $this->output->getFormatter() ?? new \PhpCodeArch\Application\CliFormatter();
        $this->output->outNl();
        $this->output->outNl(
            'Git analysis: ' . $formatter->success((string) $changes['totalCommits']) . ' commits by ' .
            $formatter->success((string) count($changes['authors'])) . ' authors (since ' . $this->since . ').'
        );
    }

    private function setDefaults(): void
    {
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'gitTotalCommits' => 0,
                'gitActiveAuthors' => 0,
                'gitAnalysisPeriod' => 'N/A',
            ]
        );
    }
}
