<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

class CloverXmlParser
{
    /**
     * Parse a Clover XML coverage file.
     *
     * @return array<string, array{linerate: float, statements: int, coveredStatements: int}>
     *                                                                                        Keyed by normalized file path (relative to project root)
     */
    public function parse(string $cloverFilePath, string $projectRoot): array
    {
        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($cloverFilePath, options: LIBXML_NONET);
        libxml_use_internal_errors($previousErrors);
        if (false === $xml) {
            return [];
        }

        $result = [];

        foreach ($xml->xpath('//file') ?: [] as $file) {
            $absPath = (string) $file['name'];
            $metrics = $file->metrics ?? null;

            if (null === $metrics) {
                continue;
            }

            $statements = (int) $metrics['statements'];
            $coveredStatements = (int) $metrics['coveredstatements'];
            $linerate = $statements > 0 ? $coveredStatements / $statements : 0.0;

            $normalizedPath = $this->normalizeFilePath($absPath, $projectRoot);
            $result[$normalizedPath] = [
                'linerate' => $linerate,
                'statements' => $statements,
                'coveredStatements' => $coveredStatements,
            ];
        }

        return $result;
    }

    private function normalizeFilePath(string $path, string $projectRoot): string
    {
        $path = str_replace('\\', '/', $path);
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

        if (str_starts_with($path, $projectRoot.'/')) {
            $path = substr($path, strlen($projectRoot) + 1);
        }

        return $path;
    }
}
