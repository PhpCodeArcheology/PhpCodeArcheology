<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\AiSummaryReport;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\HtmlReport;
use PhpCodeArch\Report\JsonReport;
use PhpCodeArch\Report\MarkdownReport;
use PhpCodeArch\Report\SarifReport;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function createSubDirTestDeps(string $reportType): array
{
    $tmpDir = sys_get_temp_dir() . '/pca-subdir-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $config = new Config();
    $config->set('reportType', $reportType);
    $config->set('reportDir', $tmpDir);

    $dataProviderFactory = Mockery::mock(DataProviderFactory::class);
    $twigLoader = Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing();
    $twig = Mockery::mock(Environment::class)->shouldIgnoreMissing();
    $output = new CliOutput();

    return [$config, $dataProviderFactory, false, $twigLoader, $twig, $output, $tmpDir];
}

afterEach(function () {
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tmpDir);
    }
});

it('HtmlReport uses html subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = createSubDirTestDeps('html');
    $this->tmpDir = $tmpDir;

    $report = new HtmlReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/html/');
});

it('MarkdownReport uses markdown subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = createSubDirTestDeps('markdown');
    $this->tmpDir = $tmpDir;

    $report = new MarkdownReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/markdown/');
});

it('JsonReport uses json subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = createSubDirTestDeps('json');
    $this->tmpDir = $tmpDir;

    $report = new JsonReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/json/');
});

it('SarifReport uses sarif subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = createSubDirTestDeps('sarif');
    $this->tmpDir = $tmpDir;

    $report = new SarifReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/sarif/');
});

it('AiSummaryReport uses ai-summary subdirectory', function () {
    [$config, $dpf, $hd, $tl, $tw, $out, $tmpDir] = createSubDirTestDeps('ai-summary');
    $this->tmpDir = $tmpDir;

    $report = new AiSummaryReport($config, $dpf, $hd, $tl, $tw, $out);

    $ref = new ReflectionProperty($report, 'outputDir');
    expect($ref->getValue($report))->toContain('/ai-summary/');
});
