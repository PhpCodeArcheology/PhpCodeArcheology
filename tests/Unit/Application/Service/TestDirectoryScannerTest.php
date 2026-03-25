<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestDirectoryScanner;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/pca-scanner-' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    // Recursive cleanup
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempDir);
});

it('returns empty result when no test directories found', function () {
    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->testDirectories)->toBe([])
        ->and($result->classBasedTestFiles)->toBe([])
        ->and($result->functionBasedTestFiles)->toBe([])
        ->and($result->testFileToType)->toBe([]);
});

it('detects test directories from PSR-4 autoload-dev config', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/FooTest.php', '<?php class FooTest {}');

    $composerJson = $this->tempDir . '/composer.json';
    $frameworkDetection = new FrameworkDetectionResult(
        composerJsonPath: $composerJson,
        psr4AutoloadDev: ['tests/'],
    );

    $scanner = new TestDirectoryScanner($frameworkDetection);
    $result = $scanner->scan($this->tempDir);

    expect($result->testDirectories)->toHaveCount(1)
        ->and(realpath($result->testDirectories[0]))->toBe(realpath($testDir));
});

it('falls back to scanning for tests directory', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/BarTest.php', '<?php class BarTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->testDirectories)->toHaveCount(1)
        ->and($result->classBasedTestFiles)->toHaveCount(1);
});

it('classifies class-based test files', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/MyTest.php', '<?php class MyTest extends TestCase {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and($result->functionBasedTestFiles)->toBe([]);
});

it('classifies function-based test files', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/FeatureTest.php', "<?php\nit('does something', function() {});");

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->functionBasedTestFiles)->toHaveCount(1)
        ->and($result->classBasedTestFiles)->toBe([]);
});

it('detects unit type from Unit directory path', function () {
    $unitDir = $this->tempDir . '/tests/Unit';
    mkdir($unitDir, 0777, true);
    file_put_contents($unitDir . '/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('unit');
});

it('detects integration type from Feature directory path', function () {
    $featureDir = $this->tempDir . '/tests/Feature';
    mkdir($featureDir, 0777, true);
    file_put_contents($featureDir . '/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('integration');
});

it('detects integration type from Integration directory path', function () {
    $integrationDir = $this->tempDir . '/tests/Integration';
    mkdir($integrationDir, 0777, true);
    file_put_contents($integrationDir . '/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('integration');
});

it('finds *Test.php files', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/FooTest.php', '<?php class FooTest {}');
    file_put_contents($testDir . '/SomeHelper.php', '<?php class SomeHelper {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('FooTest.php');
});

it('finds *Spec.php files', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/FooSpec.php', "<?php\nit('works', function() {});");

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->functionBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->functionBasedTestFiles[0]))->toBe('FooSpec.php');
});

it('finds *Cest.php files', function () {
    $testDir = $this->tempDir . '/tests';
    mkdir($testDir);
    file_put_contents($testDir . '/FooCest.php', '<?php class FooCest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('FooCest.php');
});
