<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Application\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ReportFactory
{
    /**
     * @throws ReportTypeNotSupported
     */
    public static function create(
        ?string $type,
        Config $config,
        ReportData $reportData,
        FilesystemLoader $twigLoader,
        Environment $twig
    ): ReportInterface
    {
        $type = strtolower($type ?? 'markdown');

        return match ($type) {
            'markdown' => new MarkdownReport($config, $reportData, $twigLoader, $twig),
            'html' => new HtmlReport($config, $reportData, $twigLoader, $twig),
            default => throw new ReportTypeNotSupported("Report type $type not supported."),
        };
    }
}