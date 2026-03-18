<?php

declare(strict_types=1);

namespace Test\Unit\Analysis;

use Mockery;
use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\TraitUse;

beforeEach(function() {
    $metricsContainer = new MetricsContainer();
    $this->metricsController = new MetricsController($metricsContainer);

    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '']
    );

    $this->visitor = new DependencyVisitor($this->metricsController);
    $this->visitor->setPath('');
});

it('only counts unique dependencies for file metrics', function() {
    $className = new FullyQualified('TestClass');

    $newExpression = new New_($className);
    $staticCall = new StaticCall($className, 'method');

    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($staticCall);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => ''],
        'dependencies'
    )->getAsArray();

    expect(count($dependencies))->toBeOne()
        ->and($dependencies)->toContain('TestClass');
});

it('only counts unique dependencies for class metrics', function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass']
    );

    $classNode = new Class_('ParentClass', [], ['startLine' => 1, 'endLine' => 10, 'startTokenPos' => 0, 'endTokenPos' => 10]);
    $classNode->namespacedName = new Name('ParentClass');

    $className = new FullyQualified('TestClass');
    $newExpression = new New_($className);
    $staticCall = new StaticCall($className, 'method');

    $this->visitor->enterNode($classNode);
    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($staticCall);
    $this->visitor->leaveNode($classNode);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass'],
        'dependencies'
    )->getAsArray();

    expect(count($dependencies))->toBeOne()
        ->and($dependencies)->toContain('TestClass');
});

it('only counts unique dependencies for function metrics', function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction']
    );

    $functionNode = new Function_('TestFunction');
    $functionNode->namespacedName = new Name('TestFunction');

    $className = new FullyQualified('TestClass');
    $newExpression = new New_($className);
    $staticCall = new StaticCall($className, 'method');

    $this->visitor->enterNode($functionNode);
    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($newExpression);
    $this->visitor->leaveNode($staticCall);
    $this->visitor->leaveNode($functionNode);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction'],
        'dependencies'
    )->getAsArray();

    expect(count($dependencies))->toBeOne()
        ->and($dependencies)->toContain('TestClass');
});

it('sets traits correctly and counts only unique ones', function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass']
    );

    $classNode = new Class_('ParentClass', [], ['startLine' => 1, 'endLine' => 10, 'startTokenPos' => 0, 'endTokenPos' => 10]);
    $classNode->namespacedName = new Name('ParentClass');

    $trait = new TraitUse([new Name('TestClass'), new Name('TestClass')]);

    $this->visitor->enterNode($classNode);
    $this->visitor->leaveNode($trait);
    $this->visitor->leaveNode($classNode);
    $this->visitor->afterTraverse([]);

    $traits = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass'],
        'traits'
    )->getAsArray();

    expect(count($traits))->toBeOne()
        ->and($traits)->toContain('TestClass');
});

it("doesn't count self and parent", function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass']
    );

    $classNode = new Class_('ParentClass', [], ['startLine' => 1, 'endLine' => 10, 'startTokenPos' => 0, 'endTokenPos' => 10]);
    $classNode->namespacedName = new Name('ParentClass');

    $selfExpression = new New_(new Name('self'));
    $parentExpression = new New_(new Name('parent'));

    $this->visitor->enterNode($classNode);
    $this->visitor->leaveNode($selfExpression);
    $this->visitor->leaveNode($parentExpression);
    $this->visitor->leaveNode($classNode);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '', 'name' => 'ParentClass'],
        'dependencies'
    )->getAsArray();

    expect($dependencies)->toBeEmpty();
});

it('handles function parameters with correct parameter type', function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction']
    );

    $functionNode = new Function_('TestFunction', [
        'params' => [
            new Param(new \PhpParser\Node\Expr\Variable('a')),
            new Param(new \PhpParser\Node\Expr\Variable('b'), null, new Identifier('int')),
            new Param(new \PhpParser\Node\Expr\Variable('c'), null, new FullyQualified('TestClass')),
        ],
    ]);
    $functionNode->namespacedName = new Name('TestFunction');

    $this->visitor->enterNode($functionNode);
    $this->visitor->leaveNode($functionNode);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction'],
        'dependencies'
    )->getAsArray();

    expect(count($dependencies))->toBeOne()
        ->and($dependencies)->toContain('TestClass');
});

it('handles function return value with correct return type', function() {
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction']
    );

    $functionNode = new Function_('TestFunction', [
        'returnType' => new FullyQualified('TestClass'),
    ]);
    $functionNode->namespacedName = new Name('TestFunction');

    $this->visitor->enterNode($functionNode);
    $this->visitor->leaveNode($functionNode);
    $this->visitor->afterTraverse([]);

    $dependencies = $this->metricsController->getCollection(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '', 'name' => 'TestFunction'],
        'dependencies'
    )->getAsArray();

    expect(count($dependencies))->toBeOne()
        ->and($dependencies)->toContain('TestClass');
});
