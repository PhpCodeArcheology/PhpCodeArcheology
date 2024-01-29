<?php

/**
 * Helpers for tests
 *
 * These helpers generate the metrics needed for the tests.
 *
 * @author Marcus Kober <hello@marcuskober.de>
 */

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Bootstraps the analyzer by setting up necessary metrics and parser configurations.
 *
 * @return array The array includes the source code, the metrics object, the created parser
 *               and the traverser
 */

function setupCore(): array
{
    $projectMetrics = new ProjectMetricsCollection(dirname(''));

    $metrics = new MetricsContainer();
    $metrics->set('project', $projectMetrics);

    $metricsController = new MetricsController($metrics);
    $metricsController->registerMetricTypes();

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $traverser = new NodeTraverser();

    return [
        $metricsController,
        $parser,
        $traverser,
    ];
}
function setupAnalyzer(string $file): array
{
    $code = file_get_contents($file);

    [$metricsController, $parser, $traverser] = setupCore();

    /**
     * @var MetricsController $metricsController
     */
    $metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $file]
    );

    $metricsController->createProjectMetricsCollection([$file]);

    return [
        $code,
        $metricsController,
        $parser,
        $traverser,
    ];
}

/**
 * @param array $visitors Array of Visitor class names
 * @param NodeTraverser $traverser
 * @param MetricsController $metricsController
 * @param string $file
 * @return void
 */
function setVisitors(array $visitors, NodeTraverser $traverser, MetricsController $metricsController, string $file): void
{
    $traverser->addVisitor(new NameResolver());

    foreach ($visitors as $visitor) {
        $visitorObject = new $visitor($metricsController);

        if (method_exists($visitorObject, 'init')) {
            $visitorObject->init();
        }

        if (method_exists($visitorObject, 'injectConfig')) {
            $visitorObject->injectConfig(new Config());
        }


        $visitorObject->setPath($file);
        $traverser->addVisitor($visitorObject);
    }
}

/**
 * @param string $code
 * @param Parser $parser
 * @param NodeTraverser $traverser
 * @return void
 */
function parseCode(string $code, Parser $parser, NodeTraverser $traverser): void
{
    $stmts = $parser->parse($code);
    $traverser->traverse($stmts);
}

/**
 * @param string $file
 * @param array $visitors Array of Visitor class names
 * @return MetricsController
 */
function getMetrics(string $file, array $visitors): MetricsController
{
    [$code, $metricsController, $parser, $traverser] = setupAnalyzer($file);

    setVisitors($visitors, $traverser, $metricsController, $file);
    parseCode($code, $parser, $traverser);

    return $metricsController;
}

/**
 * @param string $file
 * @param array $visitors
 * @return MetricsController
 */
function getMetricsForVisitors(string $file, array $visitors): MetricsController
{
    return getMetrics($file, $visitors);
}

function getMetricsForMultipleFilesAndVisitors(array $files, array $visitors): MetricsController
{
    [
        $metricsController,
        $parser,
        $traverser,
    ] = setupCore();

    $metricsController->createProjectMetricsCollection($files);

    $traverser->addVisitor(new NameResolver());

    $config = new Config();
    $config->set('packageSize', 2);

    $visitorObjects = [];
    foreach ($visitors as $visitor) {
        $visitorObject = new $visitor($metricsController);

        if (method_exists($visitorObject, 'init')) {
            $visitorObject->init();
        }

        if (method_exists($visitorObject, 'injectConfig')) {
            $visitorObject->injectConfig($config);
        }

        $traverser->addVisitor($visitorObject);
        $visitorObjects[] = $visitorObject;
    }

    foreach ($files as $file) {
        array_walk($visitorObjects, function($visitor) use ($file) {
            $visitor->setPath($file);
        });

        $phpCode = file_get_contents($file);

        $metricsController->createMetricCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $file]
        );

        parseCode($phpCode, $parser, $traverser);
    }

    return $metricsController;
}
