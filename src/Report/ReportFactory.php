<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\ReportDataProviderInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ReportFactory
{
    /**
     * @throws ReportTypeNotSupported
     */
    public static function create(
        Config              $config,
        DataProviderFactory   $reportDataFactory,
        false|\DateTimeImmutable $historyDate,
        FilesystemLoader    $twigLoader,
        Environment         $twig,
        CliOutput           $output
    ): ReportInterface
    {
        $type = strtolower($config->get('reportType') ?? 'markdown');

        return match ($type) {
            'markdown' => new MarkdownReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            'html' => new HtmlReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            default => throw new ReportTypeNotSupported("Report type $type not supported."),
        };
    }
}
