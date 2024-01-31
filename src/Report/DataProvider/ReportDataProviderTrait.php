<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Application\Application;
use PhpCodeArch\Repository\RepositoryInterface;

trait ReportDataProviderTrait
{
    private array $templateData = [];
    public function __construct(
        private readonly RepositoryInterface $repository)
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
