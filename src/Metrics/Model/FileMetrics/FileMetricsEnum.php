<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\FileMetrics;

enum FileMetricsEnum: string
{
    case Loc = 'loc';
    case Lloc = 'lloc';
    case Cloc = 'cloc';
    case HtmlLoc = 'htmlLoc';
    case Cc = 'cc';

    public static function keys(): array
    {
        return array_map(fn($case) => $case->name, self::cases());
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function asArray(): array {
        return array_combine(
            array_map(fn($case) => $case->name, self::cases()),
            array_map(fn($case) => $case->value, self::cases())
        );
    }
}
