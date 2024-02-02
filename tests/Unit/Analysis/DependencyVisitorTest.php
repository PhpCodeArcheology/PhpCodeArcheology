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
use PhpParser\Node\Name\FullyQualified;
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
    $newExpression = Mockery::mock(New_::class);
    $newExpression->class = 'TestClass';

    $staticCall = Mockery::mock(StaticCall::class);
    $staticCall->class = 'TestClass';

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

    $classNode = Mockery::mock(Class_::class);
    $classNode->namespacedName = 'ParentClass';

    $newExpression = Mockery::mock(New_::class);
    $newExpression->class = 'TestClass';

    $staticCall = Mockery::mock(StaticCall::class);
    $staticCall->class = 'TestClass';

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

    $functionNode = Mockery::mock(Function_::class);
    $functionNode->namespacedName = 'TestFunction';
    $functionNode->shouldReceive('getParams')
        ->andReturn([]);

    $newExpression = Mockery::mock(New_::class);
    $newExpression->class = 'TestClass';

    $staticCall = Mockery::mock(StaticCall::class);
    $staticCall->class = 'TestClass';

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

    $classNode = Mockery::mock(Class_::class);
    $classNode->namespacedName = 'ParentClass';

    $trait = Mockery::mock(TraitUse::class);

    // A real trait and a wrong trait
    $trait->traits = ['TestClass', false, 'TestClass'];

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

    $classNode = Mockery::mock(Class_::class);
    $classNode->namespacedName = 'ParentClass';

    $selfExpression = Mockery::mock(New_::class);
    $selfExpression->class = 'self';

    $parentExpression = Mockery::mock(New_::class);
    $parentExpression->class = 'parent';

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

    $functionNode = Mockery::mock(Function_::class);
    $functionNode->namespacedName = 'TestFunction';

    $parameterWithoutType = Mockery::mock(Param::class);
    $parameterWithWrongType = Mockery::mock(Param::class);
    $parameterWithWrongType->type = 'int';
    $correctParameter = Mockery::mock(Param::class);
    $correctParameter->type = Mockery::mock(FullyQualified::class);
    $correctParameter->type->shouldReceive('__toString')
        ->andReturn('TestClass');

    $functionNode->shouldReceive('getParams')
        ->andReturn([
            $parameterWithoutType,
            $parameterWithWrongType,
            $correctParameter,
        ]);

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

    $functionNode = Mockery::mock(Function_::class);
    $functionNode->namespacedName = 'TestFunction';

    $functionNode->shouldReceive('getParams')
        ->andReturn([]);

    $functionNode->returnType = Mockery::mock(FullyQualified::class);
    $functionNode->returnType->shouldReceive('__toString')
        ->andReturn('TestClass');

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
