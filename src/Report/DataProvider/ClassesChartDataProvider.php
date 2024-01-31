<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\DataProvider;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

class ClassesChartDataProvider implements ReportDataProviderInterface
{
    use ReportDataProviderTrait;


    public function gatherData(): void
    {
        $classes = $this->repository->getMetricCollectionsByCollectionKeys(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            'classes'
        );

        $namespaces = [];
        $usedClassesOfClass = [];

        foreach ($classes as $classId => $class) {
            $classPath = explode('\\', $class->getName());
            $className = array_pop($classPath);
            $namespace = implode('', $classPath);
            if (empty($namespace)) {
                $namespace = 'global';
            }

            if (!isset($namespaces[$namespace])) {
                $namespaces[$namespace] = [];
            }

            $namespaces[$namespace][] = $className;

            $usedClasses = $class->getCollection('usedClasses')?->getAsArray() ?? [];

            foreach ($usedClasses as $usedClass) {
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

                if (! isset($usedClassesOfClass[$classId])) {
                    $usedClassesOfClass[$classId] = [];
                }
                $usedClassesOfClass[$classId][] = $className;
            }
        }


        $this->templateData['classes'] = $classes;
        $this->templateData['namespaces'] = $namespaces;
        $this->templateData['usedClassesOfClass'] = $usedClassesOfClass;
    }
}
