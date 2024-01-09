<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\Data\ReportData;
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
        Environment $twig,
        CliOutput $output
    ): ReportInterface
    {
        $type = strtolower($type ?? 'markdown');

        return match ($type) {
            'markdown' => new MarkdownReport($config, $reportData, $twigLoader, $twig, $output),
            'html' => new HtmlReport($config, $reportData, $twigLoader, $twig, $output),
            default => throw new ReportTypeNotSupported("Report type $type not supported."),
        };
    }
}
