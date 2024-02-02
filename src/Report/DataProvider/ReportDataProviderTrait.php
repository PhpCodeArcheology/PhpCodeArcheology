<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Application\Application;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

trait ReportDataProviderTrait
{
    private array $templateData = [];
    public function __construct(
        private readonly MetricsController $metricsController)
    {
        $this->templateData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->templateData['version'] = Application::VERSION;
        $this->templateData['commonPath'] = $this->metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'commonPath'
        )->getValue();
        $this->gatherData();
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }
}
