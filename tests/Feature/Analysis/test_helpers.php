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
 * @param string $file
 * @return array The array includes the source code, the metrics object, the created parser
 *               and the traverser
 */

function setupCore(): array
{
    $projectMetrics = new ProjectMetricsCollection(dirname(''));

    $metrics = new MetricsContainer();
    $metrics->set('project', $projectMetrics);

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $traverser = new NodeTraverser();

    return [
        $metrics,
        $parser,
        $traverser,
    ];
}
function setupAnalyzer(string $file): array
{
    $code = file_get_contents($file);

    [$metrics, $parser, $traverser] = setupCore();

    $fileMetrics = new FileMetricsCollection($file);
    $metrics->push($fileMetrics);

    return [
        $code,
        $metrics,
        $parser,
        $traverser,
    ];
}

/**
 * @param array $visitors Array of Visitor class names
 * @param NodeTraverser $traverser
 * @param MetricsContainer $metrics
 * @param string $file
 * @return void
 */
function setVisitors(array $visitors, NodeTraverser $traverser, MetricsContainer $metrics, string $file): void
{
    $traverser->addVisitor(new NameResolver());

    foreach ($visitors as $visitor) {
        $visitorObject = new $visitor($metrics);
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
 * @return MetricsContainer
 */
function getMetrics(string $file, array $visitors): MetricsContainer
{
    [$code, $metrics, $parser, $traverser] = setupAnalyzer($file);

    setVisitors($visitors, $traverser, $metrics, $file);

    parseCode($code, $parser, $traverser);

    return $metrics;
}

/**
 * @param string $file
 * @param array $visitors
 * @return MetricsContainer
 */
function getMetricsForVisitors(string $file, array $visitors): MetricsContainer
{
    return getMetrics($file, $visitors);
}

function getMetricsForMultipleFilesAndVisitors(array $files, array $visitors): MetricsContainer
{
    [
        $metrics,
        $parser,
        $traverser,
    ] = setupCore();

    $traverser->addVisitor(new NameResolver());

    $visitorObjects = [];
    foreach ($visitors as $visitor) {
        $visitorObject = new $visitor($metrics);
        $traverser->addVisitor($visitorObject);
        $visitorObjects[] = $visitorObject;
    }

    foreach ($files as $file) {
        array_walk($visitorObjects, function($visitor) use ($file) {
            $visitor->setPath($file);
        });

        $phpCode = file_get_contents($file);

        $fileMetrics = new FileMetricsCollection($file);
        $metrics->push($fileMetrics);

        parseCode($phpCode, $parser, $traverser);
    }

    return $metrics;
}
