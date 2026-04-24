<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function createMarkdownTwig(): Environment
{
    $templateDir = dirname(__DIR__, 3).'/templates/markdown';
    $loader = new FilesystemLoader($templateDir);
    $loader->addPath($templateDir.'/parts', 'Parts');

    return new Environment($loader);
}

it('problems.md links to .md problem files, not .html', function () {
    $twig = createMarkdownTwig();
    $output = $twig->render('problems.html.twig', [
        'fileProblems' => [],
        'classProblems' => [],
        'functionProblems' => [],
        'version' => 'test',
        'createDate' => '2026-04-24',
        'currentPage' => 'problems.md',
    ]);

    expect($output)->toContain('(file-problems.md)')
        ->and($output)->toContain('(class-problems.md)')
        ->and($output)->toContain('(function-problems.md)')
        ->and($output)->not->toContain('.html)');
});

it('footer navigation does not duplicate the Packages heading', function () {
    $twig = createMarkdownTwig();
    $output = $twig->render('parts/navi.md.twig', [
        'currentPage' => 'index.md',
        'isSubdir' => false,
    ]);

    expect(substr_count($output, '**Packages**'))->toBe(1)
        ->and($output)->toContain('**Problems**');
});

it('footer navigation links each problem type to its own page', function () {
    $twig = createMarkdownTwig();
    $output = $twig->render('parts/navi.md.twig', [
        'currentPage' => 'index.md',
        'isSubdir' => false,
    ]);

    expect($output)->toContain('[File problems](file-problems.md)')
        ->and($output)->toContain('[Class problems](class-problems.md)')
        ->and($output)->toContain('[Function problems](function-problems.md)');
});

it('refactoring-roadmap renders class fullName in table rows', function () {
    $twig = createMarkdownTwig();
    $output = $twig->render('refactoring-roadmap.html.twig', [
        'refactoringPriorities' => [
            [
                'fullName' => 'App\\Service\\ComplexService',
                'score' => 42.5,
                'drivers' => ['high complexity', 'low cohesion'],
                'cc' => 30,
                'lcom' => 5.0,
                'lloc' => 200,
                'usedFromOutsideCount' => 2,
                'recommendation' => 'Break into smaller services.',
            ],
        ],
        'version' => 'test',
        'createDate' => '2026-04-24',
        'currentPage' => 'refactoring-roadmap.md',
    ]);

    // Roadmap table must show the FQN — the bug was an empty Class cell.
    expect($output)->toContain('| App\\Service\\ComplexService |');
});
