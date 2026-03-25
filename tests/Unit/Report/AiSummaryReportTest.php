<?php

declare(strict_types=1);

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\AiSummaryReport;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\GitDataProvider;
use PhpCodeArch\Report\DataProvider\ProblemDataProvider;
use PhpCodeArch\Report\DataProvider\ProjectDataProvider;
use PhpCodeArch\Report\DataProvider\RefactoringPriorityDataProvider;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makeAiSummaryDeps(): array
{
    $tmpDir = sys_get_temp_dir() . '/pca-ai-summary-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $config = new Config();
    $config->set('reportType', 'ai-summary');
    $config->set('reportDir', $tmpDir);

    $projectProvider = Mockery::mock(ProjectDataProvider::class);
    $projectProvider->shouldReceive('getTemplateData')->andReturn([
        'createDate' => '2026-01-01T00:00:00+00:00',
        'version'    => '2.5.0',
        'commonPath' => '/src',
        'elements'   => [],
    ]);

    $problemProvider = Mockery::mock(ProblemDataProvider::class);
    $problemProvider->shouldReceive('getTemplateData')->andReturn([
        'fileProblems'     => [],
        'classProblems'    => [],
        'functionProblems' => [],
    ]);

    $gitProvider = Mockery::mock(GitDataProvider::class);
    $gitProvider->shouldReceive('getTemplateData')->andReturn([
        'gitTotalCommits'   => 50,
        'gitActiveAuthors'  => 2,
        'gitAnalysisPeriod' => '6 months',
        'hotspots'          => [],
    ]);

    $refactoringProvider = Mockery::mock(RefactoringPriorityDataProvider::class);
    $refactoringProvider->shouldReceive('getTemplateData')->andReturn([
        'refactoringPriorities' => [],
    ]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')->andReturn($projectProvider);
    $factory->shouldReceive('getProblemDataProvider')->andReturn($problemProvider);
    $factory->shouldReceive('getGitDataProvider')->andReturn($gitProvider);
    $factory->shouldReceive('getRefactoringPriorityDataProvider')->andReturn($refactoringProvider);

    $output = Mockery::mock(CliOutput::class)->shouldIgnoreMissing();
    $output->shouldReceive('getFormatter')->andReturn(null);

    return [
        $config,
        $factory,
        false,
        Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing(),
        Mockery::mock(Environment::class)->shouldIgnoreMissing(),
        $output,
        $tmpDir,
    ];
}

afterEach(function () {
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tmpDir);
    }
});

it('generates an ai-summary.md file', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeAiSummaryDeps();
    $this->tmpDir = $tmpDir;

    (new AiSummaryReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    expect(file_exists($tmpDir . '/ai-summary/ai-summary.md'))->toBeTrue();
});

it('includes executive summary section', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeAiSummaryDeps();
    $this->tmpDir = $tmpDir;

    (new AiSummaryReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $content = file_get_contents($tmpDir . '/ai-summary/ai-summary.md');

    expect($content)->toContain('## Executive Summary');
});

it('includes top problems section', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeAiSummaryDeps();
    $this->tmpDir = $tmpDir;

    (new AiSummaryReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $content = file_get_contents($tmpDir . '/ai-summary/ai-summary.md');

    expect($content)->toContain('## Top Problems');
});

it('includes top hotspots section', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeAiSummaryDeps();
    $this->tmpDir = $tmpDir;

    (new AiSummaryReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $content = file_get_contents($tmpDir . '/ai-summary/ai-summary.md');

    expect($content)->toContain('## Hotspots');
});

it('shows "no problems" when problem list is empty', function () {
    [$config, $factory, $hd, $tl, $twig, $output, $tmpDir] = makeAiSummaryDeps();
    $this->tmpDir = $tmpDir;

    (new AiSummaryReport($config, $factory, $hd, $tl, $twig, $output))->generate();

    $content = file_get_contents($tmpDir . '/ai-summary/ai-summary.md');

    expect($content)->toContain('No problems detected.');
});

it('lists hotspot entries when hotspots data is provided', function () {
    $tmpDir = sys_get_temp_dir() . '/pca-ai-summary-hotspots-' . uniqid();
    mkdir($tmpDir, 0755, true);
    $this->tmpDir = $tmpDir;

    $config = new Config();
    $config->set('reportType', 'ai-summary');
    $config->set('reportDir', $tmpDir);

    $projectProvider = Mockery::mock(ProjectDataProvider::class);
    $projectProvider->shouldReceive('getTemplateData')->andReturn(['createDate' => '', 'version' => '', 'commonPath' => '', 'elements' => []]);

    $problemProvider = Mockery::mock(ProblemDataProvider::class);
    $problemProvider->shouldReceive('getTemplateData')->andReturn(['fileProblems' => [], 'classProblems' => [], 'functionProblems' => []]);

    $gitProvider = Mockery::mock(GitDataProvider::class);
    $gitProvider->shouldReceive('getTemplateData')->andReturn([
        'hotspots' => [
            ['name' => 'HotFile.php', 'churn' => 20, 'cc' => 5, 'authors' => 2],
        ],
    ]);

    $refactoringProvider = Mockery::mock(RefactoringPriorityDataProvider::class);
    $refactoringProvider->shouldReceive('getTemplateData')->andReturn(['refactoringPriorities' => []]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')->andReturn($projectProvider);
    $factory->shouldReceive('getProblemDataProvider')->andReturn($problemProvider);
    $factory->shouldReceive('getGitDataProvider')->andReturn($gitProvider);
    $factory->shouldReceive('getRefactoringPriorityDataProvider')->andReturn($refactoringProvider);

    $output = Mockery::mock(CliOutput::class)->shouldIgnoreMissing();
    $output->shouldReceive('getFormatter')->andReturn(null);

    (new AiSummaryReport(
        $config,
        $factory,
        false,
        Mockery::mock(FilesystemLoader::class)->shouldIgnoreMissing(),
        Mockery::mock(Environment::class)->shouldIgnoreMissing(),
        $output
    ))->generate();

    $content = file_get_contents($tmpDir . '/ai-summary/ai-summary.md');

    expect($content)->toContain('HotFile.php');
});
