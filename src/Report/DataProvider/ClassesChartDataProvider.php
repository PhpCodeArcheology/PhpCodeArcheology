<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

class ClassesChartDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;


    public function gatherData(): void
    {
        $classes = $this->reportDataContainer->get('classes')->getAll();

        $namespaces = [];

        $classes = array_map(function($class) use (&$namespaces) {
            $classPath = explode('\\', $class['name']);
            $className = array_pop($classPath);
            $namespace = implode('', $classPath);
            if (empty($namespace)) {
                $namespace = 'global';
            }

            if (!isset($namespaces[$namespace])) {
                $namespaces[$namespace] = [];
            }

            $namespaces[$namespace][] = $className;

            foreach ($class['classUses'] as &$usedClass) {
                $classPath = explode('\\', $usedClass);
                $className = array_pop($classPath);
                $namespace = implode('', $classPath);
                if (empty($namespace)) {
                    $namespace = 'global';
                }

                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = [];
                }

                if (!in_array($className, $namespaces[$namespace])) {
                    $namespaces[$namespace][] = $className;
                }

                $usedClass = $className;
            }

            return $class;
        }, $classes);

        $this->templateData['classes'] = $classes;
        $this->templateData['namespaces'] = $namespaces;
    }
}
