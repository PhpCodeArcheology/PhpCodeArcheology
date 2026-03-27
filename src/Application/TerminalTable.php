<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class TerminalTable
{
    /** @var array<int|string, string|int|float|bool|null> */
    private array $headers = [];
    /** @var array<int, array<int|string, string|int|float|bool|null>> */
    private array $rows = [];
    /** @var array<int, \Closure(string, string): string> */
    private array $columnFormatters = [];

    public function __construct(
        private readonly CliOutput $output,
        private readonly CliFormatter $formatter,
    ) {
    }

    /**
     * @param array<int|string, string|int|float|bool|null> $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param array<int|string, string|int|float|bool|null> $row
     */
    public function addRow(array $row): self
    {
        $this->rows[] = $row;

        return $this;
    }

    public function setColumnFormatter(int $col, \Closure $fn): self
    {
        $this->columnFormatters[$col] = $fn;

        return $this;
    }

    public function render(): void
    {
        if ([] === $this->headers) {
            return;
        }

        $colWidths = $this->calculateColumnWidths();
        $separator = $this->buildSeparator($colWidths);

        $this->output->outNl($separator);
        $this->output->outNl($this->buildRow($this->headers, $colWidths, true));
        $this->output->outNl($separator);

        foreach ($this->rows as $row) {
            $this->output->outNl($this->buildRow($row, $colWidths, false));
        }

        $this->output->outNl($separator);
    }

    /**
     * @return array<int|string, int>
     */
    private function calculateColumnWidths(): array
    {
        $widths = [];

        foreach ($this->headers as $i => $header) {
            $widths[$i] = mb_strlen((string) $header);
        }

        foreach ($this->rows as $row) {
            foreach ($row as $i => $cell) {
                $cellStr = (string) $cell;
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen($cellStr));
            }
        }

        return $widths;
    }

    /**
     * @param array<int|string, int> $colWidths
     */
    private function buildSeparator(array $colWidths): string
    {
        $parts = [];
        foreach ($colWidths as $width) {
            $parts[] = str_repeat("\u{2500}", $width + 2);
        }

        return "\u{2500}".implode("\u{2500}", $parts)."\u{2500}";
    }

    /**
     * @param array<int|string, string|int|float|bool|null> $cells
     * @param array<int|string, int>                        $colWidths
     */
    private function buildRow(array $cells, array $colWidths, bool $isHeader): string
    {
        $parts = [];

        foreach ($colWidths as $i => $width) {
            $cell = (string) ($cells[$i] ?? '');
            $padded = $this->padCell($cell, $width);

            if ($isHeader) {
                $padded = $this->formatter->bold($padded);
            } elseif (isset($this->columnFormatters[$i])) {
                $padded = ($this->columnFormatters[$i])((string) ($cells[$i] ?? ''), $padded);
            }

            $parts[] = ' '.$padded.' ';
        }

        return "\u{2502}".implode("\u{2502}", $parts)."\u{2502}";
    }

    private function padCell(string $cell, int $width): string
    {
        $len = mb_strlen($cell);
        if ($len >= $width) {
            return $cell;
        }

        if (is_numeric($cell)) {
            return str_repeat(' ', $width - $len).$cell;
        }

        return $cell.str_repeat(' ', $width - $len);
    }
}
