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
        $this->templateData['files'] = $files;
        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
