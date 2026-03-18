<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\TerminalTable;

beforeEach(function () {
    $this->buffer = '';
    $this->formatter = new CliFormatter(false);

    // Create a mock CliOutput that captures output to a buffer
    $this->output = new class extends CliOutput {
        public string $buffer = '';

        public function out(string $message): static
        {
            $this->buffer .= $message;
            return $this;
        }

        public function outNl(string $message = ''): static
        {
            $this->buffer .= PHP_EOL . $message;
            return $this;
        }
    };
});

it('renders headers and rows', function () {
    $table = new TerminalTable($this->output, $this->formatter);
    $table->setHeaders(['Name', 'Value']);
    $table->addRow(['foo', 'bar']);
    $table->render();

    $output = $this->output->buffer;

    expect($output)->toContain('Name')
        ->and($output)->toContain('Value')
        ->and($output)->toContain('foo')
        ->and($output)->toContain('bar')
        ->and($output)->toContain("\u{2502}") // vertical box char
        ->and($output)->toContain("\u{2500}"); // horizontal box char
});

it('auto-sizes columns to content width', function () {
    $table = new TerminalTable($this->output, $this->formatter);
    $table->setHeaders(['A', 'B']);
    $table->addRow(['short', 'x']);
    $table->addRow(['a', 'longer-value']);
    $table->render();

    $output = $this->output->buffer;
    $lines = explode(PHP_EOL, $output);

    // Find the header row (contains the column separator and header text)
    $headerRow = '';
    $dataRows = [];
    foreach ($lines as $line) {
        if (str_contains($line, 'A') && str_contains($line, 'B')) {
            $headerRow = $line;
        }
        if (str_contains($line, 'short')) {
            $dataRows[] = $line;
        }
        if (str_contains($line, 'longer-value')) {
            $dataRows[] = $line;
        }
    }

    // All data-bearing rows should have the same length, proving uniform column widths
    expect($headerRow)->not->toBeEmpty();
    foreach ($dataRows as $row) {
        expect(mb_strlen($row))->toBe(mb_strlen($headerRow));
    }
});

it('right-aligns numeric cells', function () {
    $table = new TerminalTable($this->output, $this->formatter);
    $table->setHeaders(['Label', 'Count']);
    $table->addRow(['items', '42']);
    $table->addRow(['things', '1234']);
    $table->render();

    $output = $this->output->buffer;

    // Find the row with '42' -- it should have leading spaces before the number
    $lines = explode(PHP_EOL, $output);
    $row42 = '';
    foreach ($lines as $line) {
        if (str_contains($line, '42') && str_contains($line, 'items')) {
            $row42 = $line;
            break;
        }
    }

    // '42' should be right-aligned (padded on left) to match width of '1234'
    expect($row42)->toMatch('/\s+42\s/');
});

it('applies column formatters', function () {
    $table = new TerminalTable($this->output, $this->formatter);
    $table->setHeaders(['Name', 'Score']);
    $table->addRow(['test', 'high']);

    // Set a formatter on column 1 that wraps the value in brackets
    $table->setColumnFormatter(1, function (mixed $raw, string $padded): string {
        return '[' . trim($padded) . ']';
    });

    $table->render();

    $output = $this->output->buffer;

    expect($output)->toContain('[high]');
});
