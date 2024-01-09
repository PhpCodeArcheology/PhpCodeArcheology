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

use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;
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
function setupAnalyzer(string $file): array
{
    $code = file_get_contents($file);

    $projectMetrics = new ProjectMetrics(dirname($file));

    $metrics = new Metrics();
    $metrics->set('project', $projectMetrics);

    $fileMetrics = new FileMetrics($file);
    $metrics->push($fileMetrics);

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $traverser = new NodeTraverser();

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
 * @param Metrics $metrics
 * @param string $file
 * @return void
 */
function setVisitors(array $visitors, NodeTraverser $traverser, Metrics $metrics, string $file): void
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
 * @return Metrics
 */
function getMetrics(string $file, array $visitors): Metrics
{
    [$code, $metrics, $parser, $traverser] = setupAnalyzer($file);

    setVisitors($visitors, $traverser, $metrics, $file);

    parseCode($code, $parser, $traverser);

    return $metrics;
}

/**
 * @param string $file
 * @param array $visitors
 * @return Metrics
 */
function getMetricsForVisitors(string $file, array $visitors): Metrics
{
    return getMetrics($file, $visitors);
}

