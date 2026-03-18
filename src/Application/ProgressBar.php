<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

class ProgressBar
{
    private float $startTime;
    private int $current = 0;

    public function __construct(
        private readonly CliOutput    $output,
        private readonly CliFormatter $formatter,
        private readonly int          $total,
        private readonly string       $label = '',
    )
    {
        $this->startTime = microtime(true);
    }

    public function advance(int $step = 1): void
    {
        $this->current += $step;
        $this->draw();
    }

    public function finish(): void
    {
        $this->current = $this->total;
        $this->draw();
        $this->output->outNl();
    }

    private function draw(): void
    {
        $this->output->cls();

        $percent = $this->total > 0 ? ($this->current / $this->total) : 1;
        $percentInt = (int) round($percent * 100);

        $termWidth = $this->getTerminalWidth();
        $barWidth = max(10, $termWidth - strlen($this->label) - 45);

        $filled = (int) round($barWidth * $percent);
        $empty = $barWidth - $filled;

        $bar = str_repeat("\u{2588}", $filled) . str_repeat("\u{2591}", $empty);

        $eta = $this->calculateEta();
        $memory = $this->getMemory();

        $counter = number_format($this->current) . '/' . number_format($this->total);

        $percentStr = str_pad($percentInt . '%', 4, ' ', STR_PAD_LEFT);

        $line = sprintf(
            '%s [%s] %s %s %s %s',
            $this->label,
            $bar,
            $this->formatter->info($percentStr),
            $this->formatter->dim($counter),
            $eta !== '' ? $this->formatter->dim('ETA: ' . $eta) : '',
            $this->formatter->dim($memory),
        );

        $this->output->out($line);
    }

    private function calculateEta(): string
    {
        if ($this->current === 0) {
            return '';
        }

        $elapsed = microtime(true) - $this->startTime;
        $remaining = ($elapsed / $this->current) * ($this->total - $this->current);

        if ($remaining < 1) {
            return '';
        }

        if ($remaining < 60) {
            return (int) $remaining . 's';
        }

        $minutes = (int) ($remaining / 60);
        $seconds = (int) ($remaining % 60);
        return $minutes . 'm ' . $seconds . 's';
    }

    private function getMemory(): string
    {
        $bytes = memory_get_usage();
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log(max($bytes, 1), 1024));
        $factor = min($factor, count($units) - 1);

        return sprintf('%.1f %s', $bytes / pow(1024, $factor), $units[(int) $factor]);
    }

    private function getTerminalWidth(): int
    {
        static $width = null;

        if ($width !== null) {
            return $width;
        }

        $width = 80;
        if (function_exists('exec')) {
            $cols = @exec('tput cols 2>/dev/null');
            if ($cols !== false && is_numeric($cols) && (int) $cols > 0) {
                $width = (int) $cols;
            }
        }

        return $width;
    }
}
