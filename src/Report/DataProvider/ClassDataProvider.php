<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

class ClassDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;

    private array $classes;

    public function gatherData(): void
    {
        $classes = $this->metrics->get('project')->get('classes');

        array_walk($classes, function(&$class) {
           $class['methods'] = array_map(fn($methodMetric) => $methodMetric->getAll(), $class['methods']);
        });

        $this->templateData['classes'] = $classes;

        $this->classes = $classes;
    }
}
