<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

$testFunctions = require __DIR__ . '/fileprovider/test-functions-provider.php';
$testClasses = require __DIR__ . '/fileprovider/test-classes-provider.php';
$testMethods = require __DIR__ . '/fileprovider/test-methods-provider.php';
function runAnalyzer(string $file): Metrics
{
    $code = file_get_contents($file);

    $projectMetrics = new ProjectMetrics(dirname($file));

    $metrics = new Metrics();
    $metrics->set('project', $projectMetrics);

    $identifyVisitor = new IdentifyVisitor($metrics);
    $identifyVisitor->setPath($file);

    $fileMetrics = new FileMetrics($file);
    $metrics->push($fileMetrics);

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $traverser = new NodeTraverser();

    $traverser->addVisitor(new NameResolver());
    $traverser->addVisitor($identifyVisitor);

    $stmts = $parser->parse($code);
    $traverser->traverse($stmts);

    return $metrics;
}

it('detects functions correctly', function($testFile, $expected) {
    $metrics = runAnalyzer($testFile);
    $projectMetrics = $metrics->get('project');

    $functions = $metrics->get('functions');

    expect(count($functions))->toBe($expected['functionCount'])
        ->and($projectMetrics->get('OverallFunctions'))->toBe($expected['functionCount'])
        ->and(array_values($functions))->toBe($expected['functionNames']);

    $functionNames = [];
    foreach ($functions as $key => $name) {
        $functionNames[] = $metrics->get($key)->getName();
    }

    expect($functionNames)->toBe($expected['functionNames']);

})->with($testFunctions);

it('detects classes correctly', function($testFile, $expected) {
    $metrics = runAnalyzer($testFile);
    $projectMetrics = $metrics->get('project');

    $classes = $metrics->get('classes');
    $classNamesFromClassesArray = array_map(function($className) {
        return str_starts_with($className, 'anonymous') ? 'anonymous' : $className;
    }, $classes);

    expect(count($classes))->toBe($expected['classCount'])
        ->and($projectMetrics->get('OverallClasses'))->toBe($expected['classCount'])
        ->and(array_values($classNamesFromClassesArray))->toBe($expected['classNames']);

    $classNames = [];
    foreach ($classes as $key => $name) {
        $className = str_starts_with($metrics->get($key)->getName(), 'anonymous') ? 'anonymous' : $metrics->get($key)->getName();
        $classNames[] = $className;
    }

    expect($classNames)->toBe($expected['classNames']);

})->with($testClasses);

it('detects methods correctly', function($testFile, $expected) {
    $metrics = runAnalyzer($testFile);
    $projectMetrics = $metrics->get('project');

    $classes = $metrics->get('classes');

    $methods = [];
    $methodCountOfAnonymousClass = 0;
    $methodNamesOfAnonymousClass = [];
    foreach ($classes as $key => $className) {
        $classMetrics = $metrics->get($key);
        $classMethods = $classMetrics->get('methods');
        $methods = array_merge($methods, $classMethods);

        if ($classMetrics->get('anonymous')) {
            $methodCountOfAnonymousClass += count($classMethods);
            foreach ($classMethods as $methodMetric) {
                $methodNamesOfAnonymousClass[] = $methodMetric->getName();
            }
        }
    }

    $methodNames = array_map(function($method) {
        return $method->getName();
    }, array_values($methods));

    expect($projectMetrics->get('OverallMethods'))->toBe($expected['methodCount'])
        ->and(count($methods))->toBe($expected['methodCount'])
        ->and($methodNames)->toBe($expected['methodNames'])
        ->and($projectMetrics->get('OverallPublicMethods'))->toBe($expected['publicMethods'])
        ->and($projectMetrics->get('OverallPrivateMethods'))->toBe($expected['privateMethods'])
        ->and($projectMetrics->get('OverallStaticMethods'))->toBe($expected['staticMethods'])
        ->and(count($classes))->toBe($expected['classCount']);

    if (isset($expected['methodCountAnonymousClass'])) {
        expect($methodCountOfAnonymousClass)->toBe($expected['methodCountAnonymousClass'])
            ->and($methodNamesOfAnonymousClass)->toBe($expected['methodNamesAnonymousClass']);
    }

})->with($testMethods);

it('detects correct class types', function() {
    $testFile = __DIR__ . '/testfiles/class-types.php';

    $metrics = runAnalyzer($testFile);
    $projectMetrics = $metrics->get('project');

    expect(count($metrics->get('classes')))->toBe(1)
        ->and(count($metrics->get('interfaces')))->toBe(1)
        ->and(count($metrics->get('traits')))->toBe(1)
        ->and(count($metrics->get('enums')))->toBe(1);
});
