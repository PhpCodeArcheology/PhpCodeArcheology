<?php

declare(strict_types=1);

/*
 * Verifies the scandir-based metric file discovery used in
 * HtmlReport::generateGlossaryPage(). The production code avoids glob()
 * so the mechanism works inside a phar:// stream, where glob() is
 * unreliable across PHP versions.
 *
 * Expected count of 7 is hand-counted from data/metrics/:
 *   class.php, file.php, function.php, git.php, method.php, package.php, project.php
 */

function scanMetricFiles(string $dir): array
{
    return array_map(
        static fn (string $f): string => $dir.'/'.$f,
        array_values(array_filter(
            scandir($dir) ?: [],
            static fn (string $f): bool => str_ends_with($f, '.php'),
        )),
    );
}

it('finds all 7 metric definition files in data/metrics', function (): void {
    $metricsDir = dirname(__DIR__, 3).'/data/metrics';

    $files = scanMetricFiles($metricsDir);

    expect($files)->toHaveCount(7);
});

it('returns absolute paths to existing php files', function (): void {
    $metricsDir = dirname(__DIR__, 3).'/data/metrics';

    $files = scanMetricFiles($metricsDir);

    foreach ($files as $file) {
        expect(is_file($file))->toBeTrue();
        expect(str_ends_with($file, '.php'))->toBeTrue();
    }
});

it('ignores non-php files in the same directory', function (): void {
    $tmpDir = sys_get_temp_dir().'/pca-metric-scan-'.uniqid();
    mkdir($tmpDir, 0755, true);

    file_put_contents($tmpDir.'/one.php', '<?php return [];');
    file_put_contents($tmpDir.'/two.php', '<?php return [];');
    file_put_contents($tmpDir.'/readme.md', '# ignore me');
    file_put_contents($tmpDir.'/notes.txt', 'ignore too');
    file_put_contents($tmpDir.'/config.yaml', 'ignore');

    try {
        $files = scanMetricFiles($tmpDir);

        expect($files)->toHaveCount(2);
        foreach ($files as $file) {
            expect(str_ends_with($file, '.php'))->toBeTrue();
        }
    } finally {
        array_map('unlink', glob($tmpDir.'/*') ?: []);
        rmdir($tmpDir);
    }
});

it('returns list array (numeric indexes) regardless of scandir order', function (): void {
    $metricsDir = dirname(__DIR__, 3).'/data/metrics';

    $files = scanMetricFiles($metricsDir);

    expect(array_keys($files))->toEqual(range(0, count($files) - 1));
});
