<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

class PackagesDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;


    public function gatherData(): void
    {
        $packages = $this->metrics->get('packages');

        $packages = array_map(function($packageName) {
            return $this->metrics->get($packageName);
        }, $packages);

        $this->templateData['packages'] = array_filter($packages, fn($metric) => $metric !== null);
    }
}
