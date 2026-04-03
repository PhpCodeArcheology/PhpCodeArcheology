<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\FileList;

// --- Helpers ---

function makeTree(string $base, array $paths): void
{
    foreach ($paths as $path) {
        $full = $base.DIRECTORY_SEPARATOR.$path;
        $dir = is_string(pathinfo($full, PATHINFO_EXTENSION)) && '' !== pathinfo($full, PATHINFO_EXTENSION)
            ? dirname($full)
            : $full;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if ('' !== pathinfo($full, PATHINFO_EXTENSION)) {
            file_put_contents($full, '<?php // '.basename($full));
        }
    }
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// --- getFiles() before fetch() ---

it('returns empty array before fetch() is called', function () {
    $config = new Config();
    $fileList = new FileList($config);

    expect($fileList->getFiles())->toBe([]);
});

// --- Empty / missing files config ---

it('returns empty array when files config is not set', function () {
    $config = new Config();
    $fileList = new FileList($config);
    $fileList->fetch();

    expect($fileList->getFiles())->toBe([]);
});

it('returns empty array when files config is empty', function () {
    $config = new Config();
    $config->set('files', []);
    $fileList = new FileList($config);
    $fileList->fetch();

    expect($fileList->getFiles())->toBe([]);
});

// --- Non-existent path in files config ---

it('silently skips non-existent paths in files config', function () {
    $config = new Config();
    $config->set('files', ['/this/path/does/not/exist/xyz999']);
    $fileList = new FileList($config);
    $fileList->fetch();

    expect($fileList->getFiles())->toBe([]);
});

// --- Single file ---

it('includes a single explicitly configured file', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    mkdir($tmpDir, 0777, true);
    $file = $tmpDir.'/MyClass.php';
    file_put_contents($file, '<?php class MyClass {}');

    $config = new Config();
    $config->set('files', [$file]);
    $fileList = new FileList($config);
    $fileList->fetch();

    // realpath() resolves symlinks (e.g. /tmp → /private/tmp on macOS)
    expect($fileList->getFiles())->toBe([realpath($file)]);

    unlink($file);
    rmdir($tmpDir);
});

// --- Recursive directory scanning ---

it('recursively scans directories and finds all .php files', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'ClassA.php',
        'sub/ClassB.php',
        'sub/deep/ClassC.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();
    sort($files);

    // Exactly 3 .php files, spread across 3 depth levels
    expect($files)->toHaveCount(3);
    expect($files[0])->toEndWith('ClassA.php');
    expect($files[1])->toEndWith('ClassB.php');
    expect($files[2])->toEndWith('ClassC.php');

    removeTree($tmpDir);
});

// --- Extension filtering ---

it('only returns .php files by default', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'MyClass.php',
        'readme.md',
        'style.css',
        'script.js',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');

    removeTree($tmpDir);
});

it('returns only files matching custom extensions', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'MyClass.php',
        'template.twig',
        'readme.md',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $config->set('extensions', ['twig']);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('template.twig');

    removeTree($tmpDir);
});

it('returns files matching any of multiple configured extensions', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'MyClass.php',
        'template.twig',
        'readme.md',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $config->set('extensions', ['php', 'twig']);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();
    sort($files);

    // php + twig = 2 files; md is excluded
    expect($files)->toHaveCount(2);
    expect($files[0])->toEndWith('MyClass.php');
    expect($files[1])->toEndWith('template.twig');

    removeTree($tmpDir);
});

// --- Default excludes ---

it('automatically excludes vendor/ relative to CWD', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        'vendor/VendorFile.php',
    ]);

    $original = getcwd();
    chdir($tmpDir);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    chdir($original);

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');
    expect($files[0])->not->toContain('vendor');

    removeTree($tmpDir);
});

it('automatically excludes node_modules/ relative to CWD', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        'node_modules/SomeModule.php',
    ]);

    $original = getcwd();
    chdir($tmpDir);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    chdir($original);

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');
    expect($files[0])->not->toContain('node_modules');

    removeTree($tmpDir);
});

it('automatically excludes .git/ relative to CWD', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        '.git/HookFile.php',
    ]);

    $original = getcwd();
    chdir($tmpDir);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    chdir($original);

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');
    expect($files[0])->not->toContain('.git');

    removeTree($tmpDir);
});

// --- User-configured excludes ---

it('excludes user-configured absolute directory paths', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        'generated/CachedClass.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $config->set('exclude', [$tmpDir.'/generated']);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');
    expect($files[0])->not->toContain('generated');

    removeTree($tmpDir);
});

it('excludes multiple user-configured directories', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        'generated/CachedClass.php',
        'cache/CacheFile.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $config->set('exclude', [
        $tmpDir.'/generated',
        $tmpDir.'/cache',
    ]);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    // Only src/MyClass.php survives
    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');

    removeTree($tmpDir);
});

// --- Merging default and user excludes ---

it('merges default excludes with user-configured excludes', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
        'vendor/VendorFile.php',    // hit by default exclude (via chdir)
        'generated/CachedClass.php', // hit by user exclude
    ]);

    $original = getcwd();
    chdir($tmpDir);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $config->set('exclude', [$tmpDir.'/generated']);
    $fileList = new FileList($config);
    $fileList->fetch();

    chdir($original);

    $files = $fileList->getFiles();

    // vendor excluded by default, generated excluded by user config → only src/MyClass.php
    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');

    removeTree($tmpDir);
});

// --- Non-existent exclude paths are silently skipped ---

it('silently ignores non-existent paths in exclude config', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    // This path does not exist — realpath() returns false → should be skipped
    $config->set('exclude', ['/this/path/does/not/exist/abc999']);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    // No crash, MyClass.php still included
    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');

    removeTree($tmpDir);
});

// --- removeFiles() ---

it('removeFiles() removes specified paths from the file list', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'ClassA.php',
        'ClassB.php',
        'ClassC.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();
    sort($files);
    // 3 files found: ClassA, ClassB, ClassC
    expect($files)->toHaveCount(3);

    // Remove ClassB
    $fileList->removeFiles([$files[1]]); // ClassB.php is index 1 after sort

    $remaining = $fileList->getFiles();
    expect($remaining)->toHaveCount(2);
    expect(array_values($remaining))->not->toContain($files[1]);

    removeTree($tmpDir);
});

it('removeFiles() with empty array leaves list unchanged', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'ClassA.php',
        'ClassB.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    $fileList = new FileList($config);
    $fileList->fetch();

    $before = $fileList->getFiles();
    $fileList->removeFiles([]);
    $after = $fileList->getFiles();

    expect($after)->toHaveCount(count($before));

    removeTree($tmpDir);
});

// --- Non-string values in exclude config are ignored ---

it('ignores non-string entries in the exclude config', function () {
    $tmpDir = sys_get_temp_dir().'/phpfilelist_'.uniqid();
    makeTree($tmpDir, [
        'src/MyClass.php',
    ]);

    $config = new Config();
    $config->set('files', [$tmpDir]);
    // Mixed types — only strings are valid, others are filtered out
    $config->set('exclude', [42, null, true, $tmpDir.'/nonexistent']);
    $fileList = new FileList($config);
    $fileList->fetch();

    $files = $fileList->getFiles();

    // No crash, MyClass.php is found
    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('MyClass.php');

    removeTree($tmpDir);
});
