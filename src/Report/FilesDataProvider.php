<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

class FilesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $files;

    public function gatherData(): void
    {
        $files = $this->metrics->get('project')->get('files');

        uasort($files, function($a, $b) {
           if ($a['name'] === $b['name']) {
               return 0;
           }

           return strnatcasecmp($a['name'], $b['name']);
        });

        $this->templateData['files'] = $files;
        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
