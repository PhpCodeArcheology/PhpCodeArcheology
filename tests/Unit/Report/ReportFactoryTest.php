<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\AiSummaryReport;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\HtmlReport;
use PhpCodeArch\Report\JsonReport;
use PhpCodeArch\Report\MarkdownReport;
use PhpCodeArch\Report\ReportFactory;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpCodeArch\Report\SarifReport;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function createReportFactoryDeps(string $reportType): array
{
    $config = new Config();
    $config->set('reportType', $reportType);
    $config->set('reportDir', sys_get_temp_dir() . '/pca-test-' . uniqid());

    $dataProviderFactory = Mockery::mock(DataProviderFactory::class);
    $twigLoader = Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing();
    $twig = Mockery::mock(Environment::class)->shouldIgnoreMissing();
    $output = new CliOutput();

    return [$config, $dataProviderFactory, false, $twigLoader, $twig, $output];
}

it('creates HtmlReport for type html', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('html');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(HtmlReport::class);
});

it('creates MarkdownReport for type markdown', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('markdown');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(MarkdownReport::class);
});

it('creates JsonReport for type json', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('json');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(JsonReport::class);
});

it('creates SarifReport for type sarif', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('sarif');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(SarifReport::class);
});

it('creates AiSummaryReport for type ai-summary', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('ai-summary');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(AiSummaryReport::class);
});

it('throws ReportTypeNotSupported for unknown type', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('pdf');

    ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);
})->throws(ReportTypeNotSupported::class);

it('is case insensitive', function () {
    [$config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output] = createReportFactoryDeps('HTML');

    $report = ReportFactory::create($config, $dataProviderFactory, $historyDate, $twigLoader, $twig, $output);

    expect($report)->toBeInstanceOf(HtmlReport::class);
});
