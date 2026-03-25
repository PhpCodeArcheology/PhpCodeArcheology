<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\ReportComparator;

function makeComparatorReport(array $metrics = [], array $problems = []): array
{
    $formatted = [];
    foreach ($metrics as $key => $value) {
        $formatted[$key] = ['value' => $value, 'name' => $key];
    }

    return [
        'version'  => '1.0',
        'project'  => ['metrics' => $formatted],
        'problems' => $problems,
    ];
}

function makeComparatorProblem(string $entityId, string $message, string $level = 'error'): array
{
    return ['entityId' => $entityId, 'message' => $message, 'level' => $level];
}

// ── compareProblems ───────────────────────────────────────────────────────────

it('detects new problems between reports', function () {
    $before = makeComparatorReport([], [makeComparatorProblem('Foo', 'existing issue')]);
    $after  = makeComparatorReport([], [
        makeComparatorProblem('Foo', 'existing issue'),
        makeComparatorProblem('Bar', 'new issue', 'warning'),
    ]);

    $result = (new ReportComparator())->compareProblems($before, $after);

    expect($result['new'])->toHaveCount(1);
    expect($result['new'][0]['message'])->toBe('new issue');
    expect($result['resolved'])->toBeEmpty();
});

it('detects resolved problems between reports', function () {
    $before = makeComparatorReport([], [
        makeComparatorProblem('Foo', 'remaining issue'),
        makeComparatorProblem('Bar', 'fixed issue', 'warning'),
    ]);
    $after = makeComparatorReport([], [makeComparatorProblem('Foo', 'remaining issue')]);

    $result = (new ReportComparator())->compareProblems($before, $after);

    expect($result['resolved'])->toHaveCount(1);
    expect($result['resolved'][0]['message'])->toBe('fixed issue');
    expect($result['new'])->toBeEmpty();
});

it('returns empty new and resolved for identical reports', function () {
    $problem = makeComparatorProblem('Foo', 'some issue');
    $report  = makeComparatorReport([], [$problem]);

    $result = (new ReportComparator())->compareProblems($report, $report);

    expect($result['new'])->toBeEmpty();
    expect($result['resolved'])->toBeEmpty();
});

it('treats empty reports as having no diff', function () {
    $result = (new ReportComparator())->compareProblems(makeComparatorReport(), makeComparatorReport());

    expect($result['new'])->toBeEmpty();
    expect($result['resolved'])->toBeEmpty();
});

// ── compareProblemCounts ──────────────────────────────────────────────────────

it('counts problems by level for both reports', function () {
    $before = makeComparatorReport([], [
        makeComparatorProblem('A', 'e1', 'error'),
        makeComparatorProblem('B', 'w1', 'warning'),
    ]);
    $after = makeComparatorReport([], [
        makeComparatorProblem('A', 'e1', 'error'),
        makeComparatorProblem('A', 'e2', 'error'),
        makeComparatorProblem('B', 'w1', 'warning'),
    ]);

    $rows = (new ReportComparator())->compareProblemCounts($before, $after);

    $errorRow = array_values(array_filter($rows, fn($r) => $r['level'] === 'Error'))[0];
    expect($errorRow['before'])->toBe(1);
    expect($errorRow['after'])->toBe(2);
    expect($errorRow['delta'])->toBe('+1');
});

it('includes a total row in problem count comparison', function () {
    $report = makeComparatorReport([], [makeComparatorProblem('X', 'msg')]);
    $rows   = (new ReportComparator())->compareProblemCounts($report, $report);

    $totalRow = array_values(array_filter($rows, fn($r) => $r['level'] === 'Total'))[0];
    expect($totalRow)->not->toBeNull();
    expect($totalRow['delta'])->toBe('0');
});

// ── compareMetrics ────────────────────────────────────────────────────────────

it('compares numeric project metrics and computes delta', function () {
    $before = makeComparatorReport(['overallErrorCount' => 5, 'overallAvgCC' => 4.0]);
    $after  = makeComparatorReport(['overallErrorCount' => 3, 'overallAvgCC' => 6.0]);

    $rows = (new ReportComparator())->compareMetrics($before, $after);

    $errorRow = array_values(array_filter($rows, fn($r) => $r['name'] === 'overallErrorCount'))[0];
    expect($errorRow['delta'])->toBe('-2');
    // lowerIsBetter → adjustedDelta = -delta = +2 (improvement)
    expect($errorRow['adjustedDelta'])->toBe(2.0);
});

it('handles non-numeric metric values gracefully', function () {
    $before = ['version' => '1.0', 'project' => ['metrics' => ['healthScore' => ['value' => 'N/A', 'name' => 'healthScore']]], 'problems' => []];
    $after  = ['version' => '1.0', 'project' => ['metrics' => ['healthScore' => ['value' => 'N/A', 'name' => 'healthScore']]], 'problems' => []];

    $rows = (new ReportComparator())->compareMetrics($before, $after);

    $row = array_values(array_filter($rows, fn($r) => $r['name'] === 'healthScore'))[0];
    expect($row['delta'])->toBe('-');
});

it('returns empty rows when both reports have no interesting metrics', function () {
    $rows = (new ReportComparator())->compareMetrics(makeComparatorReport(), makeComparatorReport());

    expect($rows)->toBeArray()->toBeEmpty();
});
