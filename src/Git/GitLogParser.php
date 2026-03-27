<?php

declare(strict_types=1);

namespace PhpCodeArch\Git;

class GitLogParser
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function isGitRepository(): bool
    {
        $output = $this->exec('git rev-parse --is-inside-work-tree 2>/dev/null');

        return 'true' === trim($output);
    }

    /**
     * Get all file changes in the given timeframe.
     * Returns: ['totalCommits' => int, 'authors' => [...], 'files' => [path => ['commits' => int, 'authors' => [...], 'lastModified' => timestamp]]].
     *
     * @return array{totalCommits: int, authors: list<string>, files: array<string, array{commits: int, authors: array<string, true>, lastModified: int}>}
     */
    public function getFileChanges(string $since): array
    {
        $cmd = sprintf(
            'git log --since=%s --name-only --format="%%H|%%an|%%at" --diff-filter=ACMR',
            escapeshellarg($since)
        );

        $output = $this->exec($cmd);
        if ('' === $output || '0' === $output) {
            return ['totalCommits' => 0, 'authors' => [], 'files' => []];
        }

        $lines = explode("\n", trim($output));

        $totalCommits = 0;
        $allAuthors = [];
        $files = [];
        $currentAuthor = '';
        $currentTimestamp = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || '0' === $line) {
                continue;
            }

            // Commit header line: hash|author|timestamp
            if (str_contains($line, '|')) {
                $parts = explode('|', $line, 3);
                if (3 === count($parts)) {
                    ++$totalCommits;
                    $currentAuthor = $parts[1];
                    $currentTimestamp = (int) $parts[2];
                    $allAuthors[$currentAuthor] = true;
                    continue;
                }
            }

            // File path line
            $filePath = $line;
            $absolutePath = $this->projectRoot.DIRECTORY_SEPARATOR.$filePath;

            if (!isset($files[$absolutePath])) {
                $files[$absolutePath] = [
                    'commits' => 0,
                    'authors' => [],
                    'lastModified' => 0,
                ];
            }

            ++$files[$absolutePath]['commits'];
            $files[$absolutePath]['authors'][$currentAuthor] = true;

            if ($currentTimestamp > $files[$absolutePath]['lastModified']) {
                $files[$absolutePath]['lastModified'] = $currentTimestamp;
            }
        }

        return [
            'totalCommits' => $totalCommits,
            'authors' => array_keys($allAuthors),
            'files' => $files,
        ];
    }

    /**
     * Get the timestamp of the last commit that modified a file.
     */
    public function getFileLastModified(string $file): ?int
    {
        $relativePath = $this->toRelativePath($file);
        $cmd = sprintf(
            'git log -1 --format="%%at" -- %s',
            escapeshellarg($relativePath)
        );

        $output = trim($this->exec($cmd));

        return '' !== $output ? (int) $output : null;
    }

    /**
     * Get commit timeline data (commits per week).
     *
     * @return array<string, int>
     */
    public function getCommitTimeline(string $since): array
    {
        $cmd = sprintf(
            'git log --since=%s --format="%%at" --reverse',
            escapeshellarg($since)
        );

        $output = $this->exec($cmd);
        if (in_array(trim($output), ['', '0'], true)) {
            return [];
        }

        $timestamps = array_filter(array_map(intval(...), explode("\n", trim($output))));
        $weeks = [];

        foreach ($timestamps as $ts) {
            $weekKey = date('Y-W', $ts);
            $weeks[$weekKey] = ($weeks[$weekKey] ?? 0) + 1;
        }

        return $weeks;
    }

    private function exec(string $command): string
    {
        $fullCommand = sprintf(
            'cd %s && %s 2>/dev/null',
            escapeshellarg($this->projectRoot),
            $command
        );

        $result = @shell_exec($fullCommand);

        return is_string($result) ? $result : '';
    }

    private function toRelativePath(string $absolutePath): string
    {
        if (str_starts_with($absolutePath, $this->projectRoot)) {
            return ltrim(substr($absolutePath, strlen($this->projectRoot)), DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}
