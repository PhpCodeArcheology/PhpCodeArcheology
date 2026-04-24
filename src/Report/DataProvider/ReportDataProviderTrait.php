<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Application\Version;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;

trait ReportDataProviderTrait
{
    /** @var array<string, mixed> */
    private array $templateData = [];

    public function __construct(
        private readonly MetricsReaderInterface $reader,
        private readonly MetricsRegistryInterface $registry,
    ) {
        $this->templateData['createDate'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->templateData['version'] = Version::CURRENT;
        $this->templateData['commonPath'] = $this->reader->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            MetricKey::COMMON_PATH
        )?->asString() ?? '';
        $this->gatherData();
    }

    /** @return array<string, mixed> */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }
}
