<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report\DataProvider;

use Marcus\PhpLegacyAnalyzer\Application\Application;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

trait ReportDataProviderTrait
{
    private array $templateData = [];
    public function __construct(private readonly Metrics $metrics)
    {
        $this->templateData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->templateData['version'] = Application::VERSION;
        $this->gatherData();
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }
}
