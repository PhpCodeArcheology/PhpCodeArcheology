<?php

declare(strict_types=1);

namespace PhpCodeArch\Git;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ProgressBar;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class GitAnalyzer
{
    private readonly GitLogParser $parser;
    private readonly string $since;

    public function __construct(
        private readonly Config $config,
        private readonly MetricsWriterInterface $writer,
        private readonly MetricsRegistryInterface $registry,
        private readonly CliOutput $output,
    ) {
        $gitConfig = $this->config->get('git');
        if (!is_array($gitConfig)) {
            $gitConfig = [];
        }

        $rawSince = $gitConfig['since'] ?? null;
        $this->since = is_string($rawSince) ? $rawSince : '6 months ago';

        $rawRoot = $gitConfig['root'] ?? null;
        $gitRoot = is_string($rawRoot) ? $rawRoot : null;

        if (null !== $gitRoot) {
            $resolved = realpath($gitRoot);
            if (false === $resolved) {
                throw new \RuntimeException("Git root directory '$gitRoot' does not exist.");
            }
            $projectRoot = $resolved;
        } else {
            $runningDir = $this->config->get('runningDir');
            $projectRoot = is_string($runningDir) ? $runningDir : (getcwd() ?: '');
        }

        $this->parser = new GitLogParser($projectRoot);
    }

    public function analyze(): void
    {
        if (!$this->parser->isGitRepository()) {
            $formatter = $this->output->getFormatter() ?? new CliFormatter();
            $this->output->outNl($formatter->warning('No Git repository detected — skipping Git analysis.'));
            $this->setDefaults();

            return;
        }

        $this->output->cls();
        $this->output->outWithMemory('Parsing Git log...');

        $changes = $this->parser->getFileChanges($this->since);

        $totalCommits = $changes['totalCommits'];
        $authors = $changes['authors'];
        $files = $changes['files'];

        // Project-level metrics
        $this->writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'gitTotalCommits' => $totalCommits,
                'gitActiveAuthors' => count($authors),
                'gitAnalysisPeriod' => $this->since,
            ]
        );

        // Count file collections for progress bar
        $fileCollections = [];
        foreach ($this->registry->getAllCollections() as $collection) {
            if ($collection instanceof \PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection
                && '' !== $collection->getPath()) {
                $fileCollections[] = $collection;
            }
        }

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $progress = new ProgressBar($this->output, $formatter, count($fileCollections), 'Git analysis');

        $now = time();

        foreach ($fileCollections as $collection) {
            $progress->advance();

            $filePath = $collection->getPath();
            $fileData = $files[$filePath] ?? null;

            if (null !== $fileData) {
                $fileAuthors = array_keys($fileData['authors']);
                $lastModified = $fileData['lastModified'];
                $commits = $fileData['commits'];
                $ageDays = $lastModified > 0 ? (int) round(($now - $lastModified) / 86400) : 0;

                $this->writer->setMetricValuesByIdentifierString(
                    (string) $collection->getIdentifier(),
                    [
                        'gitChurnCount' => $commits,
                        'gitLastModified' => $lastModified > 0 ? date('Y-m-d', $lastModified) : '',
                        'gitCodeAgeDays' => $ageDays,
                        'gitAuthorCount' => count($fileAuthors),
                        'gitAuthors' => $fileAuthors,
                    ]
                );
            } else {
                // File wasn't changed in the timeframe — get last modified from git
                $lastModified = $this->parser->getFileLastModified($filePath);
                $ageDays = null !== $lastModified ? (int) round(($now - $lastModified) / 86400) : 0;

                $this->writer->setMetricValuesByIdentifierString(
                    (string) $collection->getIdentifier(),
                    [
                        'gitChurnCount' => 0,
                        'gitLastModified' => null !== $lastModified ? date('Y-m-d', $lastModified) : '',
                        'gitCodeAgeDays' => $ageDays,
                        'gitAuthorCount' => 0,
                        'gitAuthors' => [],
                    ]
                );
            }
        }

        $progress->finish();

        $this->output->outNl(
            'Git analysis: '.$formatter->success((string) $totalCommits).' commits by '.
            $formatter->success((string) count($authors)).' authors (since '.$this->since.').'
        );
    }

    private function setDefaults(): void
    {
        $this->writer->setMetricValues(
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
