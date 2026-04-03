<?php

declare(strict_types=1);

use PhpCodeArch\Predictions\Problems\DeadCodeProblem;
use PhpCodeArch\Predictions\Problems\DeepInheritanceProblem;
use PhpCodeArch\Predictions\Problems\DependencyCycleProblem;
use PhpCodeArch\Predictions\Problems\HotspotProblem;
use PhpCodeArch\Predictions\Problems\LowTypeCoverageProblem;
use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Predictions\Problems\SecuritySmellProblem;
use PhpCodeArch\Predictions\Problems\SolidViolationProblem;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;
use PhpCodeArch\Predictions\Problems\TooDependentProblem;
use PhpCodeArch\Predictions\Problems\TooLongProblem;
use PhpCodeArch\Predictions\Problems\TooManyParametersProblem;
use PhpCodeArch\Predictions\Problems\UntestedComplexCodeProblem;

dataset('problem_classes', [
    'DeadCodeProblem' => [DeadCodeProblem::class],
    'DeepInheritanceProblem' => [DeepInheritanceProblem::class],
    'DependencyCycleProblem' => [DependencyCycleProblem::class],
    'HotspotProblem' => [HotspotProblem::class],
    'LowTypeCoverageProblem' => [LowTypeCoverageProblem::class],
    'SecuritySmellProblem' => [SecuritySmellProblem::class],
    'SolidViolationProblem' => [SolidViolationProblem::class],
    'TooComplexProblem' => [TooComplexProblem::class],
    'TooDependentProblem' => [TooDependentProblem::class],
    'TooLongProblem' => [TooLongProblem::class],
    'TooManyParametersProblem' => [TooManyParametersProblem::class],
    'UntestedComplexCodeProblem' => [UntestedComplexCodeProblem::class],
]);

it('implements ProblemInterface', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(1, 'test message');

    expect($problem)->toBeInstanceOf(ProblemInterface::class);
})->with('problem_classes');

it('stores problem level', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(2, 'some message');

    expect($problem->getProblemLevel())->toBe(2);
})->with('problem_classes');

it('stores message', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(1, 'the message text');

    expect($problem->getMessage())->toBe('the message text');
})->with('problem_classes');

it('returns non-empty recommendation', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(1, 'msg');

    expect($problem->getRecommendation())->toBeString()->not->toBeEmpty();
})->with('problem_classes');

it('returns non-empty name', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(1, 'msg');

    expect($problem->getName())->toBeString()->not->toBeEmpty();
})->with('problem_classes');

it('returns default confidence of 1.0', function (string $class) {
    $problem = $class::ofProblemLevelAndMessage(1, 'msg');

    expect($problem->getConfidence())->toBe(1.0);
})->with('problem_classes');

it('is immutable — second instance with different level is independent', function (string $class) {
    $a = $class::ofProblemLevelAndMessage(1, 'first');
    $b = $class::ofProblemLevelAndMessage(3, 'second');

    expect($a->getProblemLevel())->toBe(1)
        ->and($b->getProblemLevel())->toBe(3)
        ->and($a->getMessage())->toBe('first')
        ->and($b->getMessage())->toBe('second');
})->with('problem_classes');

// Specific name and recommendation assertions per class

it('DeadCodeProblem has correct name and recommendation', function () {
    $p = DeadCodeProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Dead code')
        ->and($p->getRecommendation())->toBe('Remove unused private methods to reduce maintenance burden.');
});

it('DeepInheritanceProblem has correct name and recommendation', function () {
    $p = DeepInheritanceProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Deep inheritance hierarchy')
        ->and($p->getRecommendation())->toBe('Prefer composition over inheritance. Extract shared behavior into traits or services.');
});

it('DependencyCycleProblem has correct name and recommendation', function () {
    $p = DependencyCycleProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Circular dependency')
        ->and($p->getRecommendation())->toBe('Break the cycle by introducing an interface or restructuring dependencies.');
});

it('HotspotProblem has correct name and recommendation', function () {
    $p = HotspotProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Hotspot')
        ->and($p->getRecommendation())->toBe('This file is frequently changed and complex. Prioritize refactoring to reduce risk.');
});

it('LowTypeCoverageProblem has correct name and recommendation', function () {
    $p = LowTypeCoverageProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Low type coverage')
        ->and($p->getRecommendation())->toBe('Add type declarations to parameters, return types, and properties.');
});

it('SecuritySmellProblem has correct name and recommendation', function () {
    $p = SecuritySmellProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Security smell')
        ->and($p->getRecommendation())->toBe('Replace dangerous functions with safe alternatives. Use parameterized queries instead of string concatenation.');
});

it('SolidViolationProblem has correct name and recommendation', function () {
    $p = SolidViolationProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('SOLID violation')
        ->and($p->getRecommendation())->toBe('SRP: Split class responsibilities. ISP: Break large interfaces into smaller ones. DIP: Depend on abstractions.');
});

it('TooComplexProblem has correct name and recommendation', function () {
    $p = TooComplexProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Too complex code')
        ->and($p->getRecommendation())->toBe('Extract Method to reduce complexity. Break conditional logic into smaller, named methods.');
});

it('TooDependentProblem has correct name and recommendation', function () {
    $p = TooDependentProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Too dependent')
        ->and($p->getRecommendation())->toBe('Introduce interfaces to reduce coupling. Consider dependency injection.');
});

it('TooLongProblem has correct name and recommendation', function () {
    $p = TooLongProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Too long')
        ->and($p->getRecommendation())->toBe('Extract Method or split into smaller units. Each method should do one thing.');
});

it('TooManyParametersProblem has correct name and recommendation', function () {
    $p = TooManyParametersProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Too many parameters')
        ->and($p->getRecommendation())->toBe('Consider using a parameter object or builder pattern.');
});

it('UntestedComplexCodeProblem has correct name and recommendation', function () {
    $p = UntestedComplexCodeProblem::ofProblemLevelAndMessage(1, 'msg');

    expect($p->getName())->toBe('Untested Complex Code')
        ->and($p->getRecommendation())->toBe('Add tests before refactoring to prevent regressions.');
});
