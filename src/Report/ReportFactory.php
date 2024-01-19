<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ReportFactory
{
    /**
     * @throws ReportTypeNotSupported
     */
    public static function create(
        Config              $config,
        DataProviderFactory $reportData,
        FilesystemLoader    $twigLoader,
        Environment         $twig,
        CliOutput           $output
    ): ReportInterface
    {
        $type = strtolower($config->get('reportType') ?? 'markdown');

        return match ($type) {
            'markdown' => new MarkdownReport($config, $reportData, $twigLoader, $twig, $output),
            'html' => new HtmlReport($config, $reportData, $twigLoader, $twig, $output),
            default => throw new ReportTypeNotSupported("Report type $type not supported."),
        };
    }
}
