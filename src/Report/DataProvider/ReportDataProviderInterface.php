<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Repository\RepositoryInterface;

interface ReportDataProviderInterface
{
    public function __construct(RepositoryInterface $repository);

    public function getTemplateData(): array;

    public function gatherData(): void;
}
