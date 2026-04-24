<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\QuickOutput;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;

// ── Capturing CliOutput stub ──────────────────────────────────────────────────

/**
 * A CliOutput subclass that captures all output in memory instead of writing
 * to stdout. Lets us assert on QuickOutput's rendered content.
 */
class CapturingCliOutput extends CliOutput
{
    private string $captured = '';

    public function out(string $message): void
    {
        $this->captured .= $message;
    }

    public function getCaptured(): string
    {
        return $this->captured;
    }

    public function reset(): void
    {
        $this->captured = '';
    }
}

// Helpers ─────────────────────────────────────────────────────────────────────

function makeQoController(array $projectValues = []): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    if (!empty($projectValues)) {
        $controller->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $projectValues,
        );
    }

    return $controller;
}

function makeQo(MetricsController $ctrl): array
{
    $output = new CapturingCliOutput();
    $formatter = new CliFormatter(colorEnabled: false);
    $qo = new QuickOutput($ctrl, $ctrl, $output, $formatter);

    return [$qo, $output];
}

// ── Summary metrics output ────────────────────────────────────────────────────

it('outputs summary metrics (avg CC and avg MI)', function () {
    $ctrl = makeQoController([
        'overallFiles' => 20,
        'overallClasses' => 10,
        'overallMethodsCount' => 80,
        'overallLloc' => 3000,
        'overallAvgCC' => 4.5,
        'overallAvgMI' => 78.0,
    ]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('4.5')
        ->and($text)->toContain('78');
});

it('outputs project overview with files, classes, methods and lloc', function () {
    $ctrl = makeQoController([
        'overallFiles' => 42,
        'overallClasses' => 15,
        'overallMethodsCount' => 120,
        'overallLloc' => 5000,
        'overallAvgCC' => 3.0,
        'overallAvgMI' => 85.0,
    ]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('42')
        ->and($text)->toContain('15')
        ->and($text)->toContain('120')
        ->and($text)->toContain('5,000');
});

// ── Empty metrics ─────────────────────────────────────────────────────────────

it('handles empty metrics gracefully without throwing', function () {
    $ctrl = makeQoController();

    [$qo] = makeQo($ctrl);

    expect(fn () => $qo->render())->not->toThrow(Throwable::class);
});

it('outputs zero CC and MI when no metrics are set', function () {
    $ctrl = makeQoController();

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('Avg CC')
        ->and($text)->toContain('0');
});

// ── Header output ─────────────────────────────────────────────────────────────

it('renders "Quick Analysis" header every time', function () {
    $ctrl = makeQoController();

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('Quick Analysis');
});

it('renders Avg MI label in summary', function () {
    $ctrl = makeQoController(['overallAvgCC' => 2.0, 'overallAvgMI' => 90.0]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('Avg MI');
});

// ── Top files table ───────────────────────────────────────────────────────────

it('renders top files table when file collections exist', function () {
    $ctrl = makeQoController(['overallAvgCC' => 5.0, 'overallAvgMI' => 80.0]);

    for ($i = 1; $i <= 3; ++$i) {
        $path = "/src/File{$i}.php";
        $ctrl->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $path]);
        $ctrl->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $path],
            ['fileName' => "File{$i}.php", 'cc' => $i * 5, 'loc' => $i * 100, 'mi' => 75.0],
        );
    }

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('Top 10 Files by Complexity');
});

it('skips file table when no file collections exist', function () {
    $ctrl = makeQoController(['overallAvgCC' => 2.0, 'overallAvgMI' => 90.0]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->not->toContain('Top 10 Files by Complexity');
});

// ── Top classes table ────────────────────────────────────────────────────────

it('renders top classes table when class collections exist', function () {
    $ctrl = makeQoController(['overallAvgCC' => 5.0, 'overallAvgMI' => 80.0]);

    for ($i = 1; $i <= 3; ++$i) {
        $path = "/src/Class{$i}.php";
        $ctrl->createMetricCollection(
            MetricCollectionTypeEnum::ClassCollection,
            ['name' => "MyClass{$i}", 'path' => $path]
        );
        $ctrl->setMetricValues(
            MetricCollectionTypeEnum::ClassCollection,
            ['name' => "MyClass{$i}", 'path' => $path],
            ['cc' => $i * 8, 'numberOfMethods' => $i * 3, 'mi' => 70.0],
        );
    }

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->toContain('Top 10 Classes by Complexity');
});

it('skips classes table when no class collections exist', function () {
    $ctrl = makeQoController(['overallAvgCC' => 2.0, 'overallAvgMI' => 90.0]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    expect($text)->not->toContain('Top 10 Classes by Complexity');
});

// ── Color formatter integration ───────────────────────────────────────────────

it('does not emit ANSI codes when color is disabled', function () {
    $ctrl = makeQoController(['overallAvgCC' => 5.0, 'overallAvgMI' => 80.0]);

    [$qo, $output] = makeQo($ctrl);
    $qo->render();
    $text = $output->getCaptured();

    // No ANSI escape sequences
    expect($text)->not->toContain("\033[");
});
