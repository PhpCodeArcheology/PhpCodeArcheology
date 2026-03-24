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
     * @return ReportInterface[]
     * @throws ReportTypeNotSupported
     */
    public static function createMultiple(
        Config              $config,
        DataProviderFactory $reportDataFactory,
        false|\DateTimeImmutable $historyDate,
        FilesystemLoader    $twigLoader,
        Environment         $twig,
        CliOutput           $output
    ): array
    {
        $types = $config->get('reportType') ?? 'html';
        $types = is_array($types) ? array_unique($types) : [$types];

        $reports = [];
        foreach ($types as $type) {
            $singleConfig = clone $config;
            $singleConfig->set('reportType', strtolower($type));
            $reports[] = self::create($singleConfig, $reportDataFactory, $historyDate, $twigLoader, $twig, $output);
        }

        return $reports;
    }

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
            'json' => new JsonReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            'sarif' => new SarifReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            'ai-summary' => new AiSummaryReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            'graph' => new GraphReport($config, $reportDataFactory, $historyDate, $twigLoader, $twig, $output),
            default => throw new ReportTypeNotSupported("Report type $type not supported."),
        };
    }
}
