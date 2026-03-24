<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\FunctionNameCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Report\DataProvider\GraphDataProvider;

// Helper: create a MetricValue without needing a full MetricType
function graphMv(mixed $value, string $key = 'dummy'): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, $key);
}

// Helper: create a MetricsController mock suitable for most GraphDataProvider tests.
// shouldIgnoreMissing() handles all other calls (returning null), which the
// GraphDataProvider code handles via null-checks.
function makeGraphMc(array $allCollections = [], array $packages = []): \Mockery\MockInterface
{
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    // ReportDataProviderTrait constructor requires this call to return a non-null MetricValue
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getAllCollections')->andReturn($allCollections);
    // Must return iterable, not null
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn($packages);
    return $mc;
}

// ── JSON structure ────────────────────────────────────────────────────────────

it('getGraphData() returns array with nodes, edges, clusters, cycles keys', function () {
    $provider = new GraphDataProvider(makeGraphMc());

    expect($provider->getGraphData())->toHaveKeys(['nodes', 'edges', 'clusters', 'cycles']);
});

it('all result arrays are empty when no collections are provided', function () {
    $provider = new GraphDataProvider(makeGraphMc());

    expect($provider->getNodes())->toBeEmpty();
    expect($provider->getEdges())->toBeEmpty();
    expect($provider->getClusters())->toBeEmpty();
    expect($provider->getCycles())->toBeEmpty();
});

// ── No file nodes ─────────────────────────────────────────────────────────────

it('does not create file nodes', function () {
    $file = new FileMetricsCollection('/src/Foo.php');
    $file->set('loc', graphMv(100, 'loc'));

    $provider = new GraphDataProvider(makeGraphMc([$file]));
    $fileNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'file'));

    expect($fileNodes)->toBeEmpty();
});

// ── Class node ────────────────────────────────────────────────────────────────

it('creates class node from ClassMetricsCollection', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $class->set('cc', graphMv(3, 'cc'));
    $class->set('lcom', graphMv(0.5, 'lcom'));

    $provider = new GraphDataProvider(makeGraphMc([$class]));
    $nodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'class'));

    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['type'])->toBe('class');
    expect($nodes[0]['name'])->toBe('FooClass');
    expect($nodes[0]['metrics']['cc'])->toBe(3);
});

it('class node id uses class identifierString', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $classId = (string) $class->getIdentifier();

    $provider = new GraphDataProvider(makeGraphMc([$class]));
    $nodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'class'));

    expect($nodes[0]['id'])->toBe('class:' . $classId);
});

// ── Anonymous skip ────────────────────────────────────────────────────────────

it('skips classes with anonymous@ prefix', function () {
    $anon = new ClassMetricsCollection('/src/Foo.php', 'anonymous@/src/Foo.php:42');
    $real = new ClassMetricsCollection('/src/Bar.php', 'BarClass');

    $provider = new GraphDataProvider(makeGraphMc([$anon, $real]));
    $classNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'class'));

    expect($classNodes)->toHaveCount(1);
    expect($classNodes[0]['name'])->toBe('BarClass');
});

// ── Function node ─────────────────────────────────────────────────────────────

it('creates function node for standalone functions', function () {
    $fn = new FunctionMetricsCollection('/src/helpers.php', 'myFunc');
    $fn->set('cc', graphMv(2, 'cc'));
    $fn->set('parameterCount', graphMv(3, 'parameterCount'));
    $fn->set('cognitiveComplexity', graphMv(4, 'cognitiveComplexity'));
    $fn->set('functionType', graphMv('function', 'functionType'));

    $provider = new GraphDataProvider(makeGraphMc([$fn]));
    $nodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'function'));

    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['type'])->toBe('function');
    expect($nodes[0]['name'])->toBe('myFunc');
    expect($nodes[0]['metrics']['cc'])->toBe(2);
    expect($nodes[0]['metrics']['params'])->toBe(3);
    expect($nodes[0]['metrics']['cognitiveComplexity'])->toBe(4);
});

it('skips methods in function processing', function () {
    $method = new FunctionMetricsCollection('MyClass', 'doSomething');
    $method->set('functionType', graphMv('method', 'functionType'));

    $provider = new GraphDataProvider(makeGraphMc([$method]));
    $fnNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'function'));

    expect($fnNodes)->toBeEmpty();
});

// ── Method node ──────────────────────────────────────────────────────────────

it('creates method nodes with declares edge from class', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $classId = (string) $class->getIdentifier();

    $method = new FunctionMetricsCollection('FooClass', 'doWork');
    $methodId = (string) $method->getIdentifier();
    $method->set('cc', graphMv(5, 'cc'));
    $method->set('parameterCount', graphMv(2, 'parameterCount'));
    $method->set('functionType', graphMv('method', 'functionType'));
    $method->set('public', graphMv(true, 'public'));
    $method->set('private', graphMv(false, 'private'));
    $method->set('protected', graphMv(false, 'protected'));
    $method->set('static', graphMv(false, 'static'));

    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getAllCollections')->andReturn([$class, $method]);
    $mc->shouldReceive('getCollectionByIdentifierString')
        ->with($classId, 'methods')
        ->andReturn(new ClassNameCollection([$methodId => 'doWork']));
    $mc->shouldReceive('getMetricCollectionByIdentifierString')
        ->with($methodId)
        ->andReturn($method);
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn([]);

    $provider = new GraphDataProvider($mc);

    $methodNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'method'));
    expect($methodNodes)->toHaveCount(1);
    expect($methodNodes[0]['name'])->toBe('doWork');
    expect($methodNodes[0]['metrics']['cc'])->toBe(5);
    expect($methodNodes[0]['flags']['public'])->toBeTrue();

    $declaresEdges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'declares'));
    expect($declaresEdges)->toHaveCount(1);
    expect($declaresEdges[0]['source'])->toBe('class:' . $classId);
    expect($declaresEdges[0]['target'])->toBe('method:' . $methodId);
});

// ── Package node ──────────────────────────────────────────────────────────────

it('creates package node from package collection', function () {
    $pkg = new PackageMetricsCollection('MyPackage');
    $pkg->set('abstractness', graphMv(0.5, 'abstractness'));
    $pkg->set('instability', graphMv(0.3, 'instability'));
    $pkg->set('distanceFromMainline', graphMv(0.2, 'distanceFromMainline'));

    $provider = new GraphDataProvider(makeGraphMc([], [$pkg]));
    $nodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'package'));

    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['type'])->toBe('package');
    expect($nodes[0]['name'])->toBe('MyPackage');
    expect($nodes[0]['id'])->toBe('package:MyPackage');
    expect($nodes[0]['metrics']['abstractness'])->toBe(0.5);
    expect($nodes[0]['metrics']['instability'])->toBe(0.3);
});

// ── Author node ───────────────────────────────────────────────────────────────

it('creates author nodes from file gitAuthors', function () {
    $file = new FileMetricsCollection('/src/Foo.php');
    $file->set('gitAuthors', graphMv(['Alice', 'Bob'], 'gitAuthors'));
    $file->set('gitChurnCount', graphMv(5, 'gitChurnCount'));

    $provider = new GraphDataProvider(makeGraphMc([$file]));
    $authorNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'author'));

    expect($authorNodes)->toHaveCount(2);
    $names = array_map(fn($n) => $n['name'], $authorNodes);
    expect($names)->toContain('Alice');
    expect($names)->toContain('Bob');
});

// ── Git metrics on class nodes ────────────────────────────────────────────────

it('adds git metrics to class nodes from file data', function () {
    $file = new FileMetricsCollection('/src/Foo.php');
    $file->set('gitAuthors', graphMv(['Alice'], 'gitAuthors'));
    $file->set('gitChurnCount', graphMv(10, 'gitChurnCount'));
    $file->set('gitCodeAgeDays', graphMv(42, 'gitCodeAgeDays'));

    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');

    $provider = new GraphDataProvider(makeGraphMc([$file, $class]));
    $classNodes = array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'class'));

    expect($classNodes)->toHaveCount(1);
    expect($classNodes[0]['metrics']['gitChurnCount'])->toBe(10);
    expect($classNodes[0]['metrics']['gitCodeAgeDays'])->toBe(42);
});

// ── extends edge ──────────────────────────────────────────────────────────────

it('creates extends edge between classes', function () {
    $parent = new ClassMetricsCollection('/src/Base.php', 'BaseClass');
    $parentId = (string) $parent->getIdentifier();

    $child = new ClassMetricsCollection('/src/Child.php', 'ChildClass');
    $childId = (string) $child->getIdentifier();

    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getCollection')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'classes')
        ->andReturn(new ClassNameCollection([$parentId => 'BaseClass', $childId => 'ChildClass']));
    $mc->shouldReceive('getAllCollections')->andReturn([$child]);
    $mc->shouldReceive('getCollectionByIdentifierString')
        ->with($childId, 'extends')
        ->andReturn(new ClassNameCollection(['BaseClass']));
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn([]);

    $provider = new GraphDataProvider($mc);
    $edges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'extends'));

    expect($edges)->toHaveCount(1);
    expect($edges[0]['source'])->toBe('class:' . $childId);
    expect($edges[0]['target'])->toBe('class:' . $parentId);
    expect($edges[0]['type'])->toBe('extends');
});

// ── depends_on edge ───────────────────────────────────────────────────────────

it('creates depends_on edge between classes', function () {
    $dep = new ClassMetricsCollection('/src/Dep.php', 'DepClass');
    $depId = (string) $dep->getIdentifier();

    $user = new ClassMetricsCollection('/src/User.php', 'UserClass');
    $userId = (string) $user->getIdentifier();

    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getCollection')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'classes')
        ->andReturn(new ClassNameCollection([$depId => 'DepClass', $userId => 'UserClass']));
    $mc->shouldReceive('getAllCollections')->andReturn([$user]);
    $mc->shouldReceive('getCollectionByIdentifierString')
        ->with($userId, 'usedClasses')
        ->andReturn(new ClassNameCollection(['DepClass']));
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn([]);

    $provider = new GraphDataProvider($mc);
    $edges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'depends_on'));

    expect($edges)->toHaveCount(1);
    expect($edges[0]['source'])->toBe('class:' . $userId);
    expect($edges[0]['target'])->toBe('class:' . $depId);
});

// ── belongs_to edge ───────────────────────────────────────────────────────────

it('creates belongs_to edge from class to package', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $class->set('package', graphMv('MyPackage', 'package'));
    $classId = (string) $class->getIdentifier();

    $provider = new GraphDataProvider(makeGraphMc([$class]));
    $edges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'belongs_to'));

    expect($edges)->toHaveCount(1);
    expect($edges[0]['source'])->toBe('class:' . $classId);
    expect($edges[0]['target'])->toBe('package:MyPackage');
});

// ── authored_by edge ──────────────────────────────────────────────────────────

it('creates authored_by edge from class to author', function () {
    $file = new FileMetricsCollection('/src/Foo.php');
    $file->set('gitAuthors', graphMv(['Alice'], 'gitAuthors'));
    $file->set('gitChurnCount', graphMv(3, 'gitChurnCount'));

    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $classId = (string) $class->getIdentifier();

    $provider = new GraphDataProvider(makeGraphMc([$file, $class]));
    $edges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'authored_by'));

    expect($edges)->toHaveCount(1);
    expect($edges[0]['source'])->toBe('class:' . $classId);
    expect($edges[0]['target'])->toBe('author:Alice');
    expect($edges[0]['type'])->toBe('authored_by');
});

// ── Clusters ──────────────────────────────────────────────────────────────────

it('creates cluster for package with classes', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $classId = (string) $class->getIdentifier();

    $pkg = new PackageMetricsCollection('MyPackage');
    $pkg->setCollection('classes', new ClassNameCollection(['FooClass']));

    // Cluster resolution needs nameToId map → mock getCollection for 'classes' on ProjectCollection
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getCollection')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'classes')
        ->andReturn(new ClassNameCollection([$classId => 'FooClass']));
    $mc->shouldReceive('getAllCollections')->andReturn([]);
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn([$pkg]);

    $provider = new GraphDataProvider($mc);
    $clusters = $provider->getClusters();

    expect($clusters)->toHaveCount(1);
    expect($clusters[0]['id'])->toBe('package:MyPackage');
    expect($clusters[0]['name'])->toBe('MyPackage');
    expect($clusters[0]['nodeIds'])->toContain('class:' . $classId);
});

it('does not create cluster for package with no classes', function () {
    $pkg = new PackageMetricsCollection('EmptyPackage');
    // No 'classes' collection set → no cluster

    $provider = new GraphDataProvider(makeGraphMc([], [$pkg]));

    expect($provider->getClusters())->toBeEmpty();
});

// ── Cycle extraction and deduplication ───────────────────────────────────────

it('extracts and deduplicates cycles', function () {
    $classA = new ClassMetricsCollection('/src/A.php', 'ClassA');
    $classAId = (string) $classA->getIdentifier();
    $classA->set('inDependencyCycle', graphMv(true, 'inDependencyCycle'));
    $classA->set('dependencyCycleClasses', graphMv(['ClassA', 'ClassB'], 'dependencyCycleClasses'));
    $classA->set('dependencyCycleLength', graphMv(2, 'dependencyCycleLength'));

    $classB = new ClassMetricsCollection('/src/B.php', 'ClassB');
    $classBId = (string) $classB->getIdentifier();
    $classB->set('inDependencyCycle', graphMv(true, 'inDependencyCycle'));
    $classB->set('dependencyCycleClasses', graphMv(['ClassA', 'ClassB'], 'dependencyCycleClasses'));
    $classB->set('dependencyCycleLength', graphMv(2, 'dependencyCycleLength'));

    // Both classes report the same cycle — must be deduplicated to exactly one entry
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(graphMv('/project', 'commonPath'));
    $mc->shouldReceive('getCollection')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'classes')
        ->andReturn(new ClassNameCollection([$classAId => 'ClassA', $classBId => 'ClassB']));
    $mc->shouldReceive('getAllCollections')->andReturn([$classA, $classB]);
    $mc->shouldReceive('getMetricCollectionsByCollectionKeys')->andReturn([]);

    $provider = new GraphDataProvider($mc);

    expect($provider->getCycles())->toHaveCount(1);
    expect($provider->getCycles()[0]['length'])->toBe(2);
});

// ── Null safety ───────────────────────────────────────────────────────────────

it('handles null values in extends collection without errors', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'FooClass');
    $classId = (string) $class->getIdentifier();

    $mc = makeGraphMc([$class]);
    // Null entries in the collection should be silently skipped
    $mc->shouldReceive('getCollectionByIdentifierString')
        ->with($classId, 'extends')
        ->andReturn(new ClassNameCollection([null, '', null]));

    $provider = new GraphDataProvider($mc);

    expect($provider->getEdges())->toBeArray();
    $extendsEdges = array_values(array_filter($provider->getEdges(), fn($e) => $e['type'] === 'extends'));
    expect($extendsEdges)->toBeEmpty();
});

it('handles null gitAuthors value without errors', function () {
    $file = new FileMetricsCollection('/src/Foo.php');
    // gitAuthors not set → get() returns null → ?->getValue() → null ?? [] → empty array

    $provider = new GraphDataProvider(makeGraphMc([$file]));

    expect($provider->getNodes())->toBeEmpty();
    expect(array_values(array_filter($provider->getNodes(), fn($n) => $n['type'] === 'author')))->toBeEmpty();
});
