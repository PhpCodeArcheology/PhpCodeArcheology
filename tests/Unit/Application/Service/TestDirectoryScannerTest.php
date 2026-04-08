<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestDirectoryScanner;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pca-scanner-'.uniqid();
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
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/FooTest.php', '<?php class FooTest {}');

    $composerJson = $this->tempDir.'/composer.json';
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
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/BarTest.php', '<?php class BarTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->testDirectories)->toHaveCount(1)
        ->and($result->classBasedTestFiles)->toHaveCount(1);
});

it('classifies class-based test files', function () {
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/MyTest.php', '<?php class MyTest extends TestCase {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and($result->functionBasedTestFiles)->toBe([]);
});

it('classifies function-based test files', function () {
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/FeatureTest.php', "<?php\nit('does something', function() {});");

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->functionBasedTestFiles)->toHaveCount(1)
        ->and($result->classBasedTestFiles)->toBe([]);
});

it('detects unit type from Unit directory path', function () {
    $unitDir = $this->tempDir.'/tests/Unit';
    mkdir($unitDir, 0777, true);
    file_put_contents($unitDir.'/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('unit');
});

it('detects integration type from Feature directory path', function () {
    $featureDir = $this->tempDir.'/tests/Feature';
    mkdir($featureDir, 0777, true);
    file_put_contents($featureDir.'/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('integration');
});

it('detects integration type from Integration directory path', function () {
    $integrationDir = $this->tempDir.'/tests/Integration';
    mkdir($integrationDir, 0777, true);
    file_put_contents($integrationDir.'/SomeTest.php', '<?php class SomeTest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $file = array_key_first($result->testFileToType);
    expect($result->testFileToType[$file])->toBe('integration');
});

it('finds *Test.php files', function () {
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/FooTest.php', '<?php class FooTest {}');
    file_put_contents($testDir.'/SomeHelper.php', '<?php class SomeHelper {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('FooTest.php');
});

it('finds *Spec.php files', function () {
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/FooSpec.php', "<?php\nit('works', function() {});");

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->functionBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->functionBasedTestFiles[0]))->toBe('FooSpec.php');
});

it('finds *Cest.php files', function () {
    $testDir = $this->tempDir.'/tests';
    mkdir($testDir);
    file_put_contents($testDir.'/FooCest.php', '<?php class FooCest {}');

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('FooCest.php');
});

// ---------------------------------------------------------------------------
// phpunit.xml primary-path cases (the new behavior)
// ---------------------------------------------------------------------------

it('uses phpunit.xml testsuite as authoritative source when present', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Integration', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/UnitTest.php', '<?php class UnitTest {}');
    file_put_contents($this->tempDir.'/tests/Integration/IntTest.php', '<?php class IntTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="unit-only"><directory>tests/Unit</directory></testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    // Only tests/Unit/UnitTest.php should be found — tests/Integration is outside the configured suites.
    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('UnitTest.php');
});

it('honors <exclude> directory entries from phpunit.xml', function () {
    mkdir($this->tempDir.'/tests/Unit/Legacy', 0777, true);
    mkdir($this->tempDir.'/tests/Unit/Core', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/Legacy/LegacyTest.php', '<?php class LegacyTest {}');
    file_put_contents($this->tempDir.'/tests/Unit/Core/CoreTest.php', '<?php class CoreTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
      <exclude>tests/Unit/Legacy</exclude>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    expect($basenames)->toContain('CoreTest.php')
        ->and($basenames)->not->toContain('LegacyTest.php');
});

it('honors <exclude> file entries from phpunit.xml', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/GoodTest.php', '<?php class GoodTest {}');
    file_put_contents($this->tempDir.'/tests/Unit/BrokenTest.php', '<?php class BrokenTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
      <exclude>tests/Unit/BrokenTest.php</exclude>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    expect($basenames)->toContain('GoodTest.php')
        ->and($basenames)->not->toContain('BrokenTest.php');
});

it('honors per-directory suffix attribute with mixed suffixes', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Acceptance', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/UnitTest.php', '<?php class UnitTest {}');
    file_put_contents($this->tempDir.'/tests/Unit/Helper.php', '<?php class Helper {}'); // does not end in Test.php
    file_put_contents($this->tempDir.'/tests/Acceptance/LoginCest.php', '<?php class LoginCest {}');
    file_put_contents($this->tempDir.'/tests/Acceptance/Fixture.php', '<?php class Fixture {}'); // does not end in Cest.php
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="all">
      <directory>tests/Unit</directory>
      <directory suffix="Cest.php">tests/Acceptance</directory>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    sort($basenames);
    expect($basenames)->toBe(['LoginCest.php', 'UnitTest.php']);
});

it('includes explicit <file> entries even if the suffix does not match', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Special', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/RegularTest.php', '<?php class RegularTest {}');
    file_put_contents($this->tempDir.'/tests/Special/OneOffCase.php', '<?php class OneOffCase {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
      <file>tests/Special/OneOffCase.php</file>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    sort($basenames);
    expect($basenames)->toBe(['OneOffCase.php', 'RegularTest.php']);
});

it('falls back to legacy discovery when phpunit.xml is malformed', function () {
    mkdir($this->tempDir.'/tests', 0777, true);
    file_put_contents($this->tempDir.'/tests/FooTest.php', '<?php class FooTest {}');
    file_put_contents($this->tempDir.'/phpunit.xml', '<not valid xml<');

    $scanner = new TestDirectoryScanner();
    ob_start();
    $result = @$scanner->scan($this->tempDir);
    ob_end_clean();

    expect($result->classBasedTestFiles)->toHaveCount(1)
        ->and(basename($result->classBasedTestFiles[0]))->toBe('FooTest.php');
});

it('falls back to legacy discovery when phpunit.xml has empty <testsuites/>', function () {
    mkdir($this->tempDir.'/tests', 0777, true);
    file_put_contents($this->tempDir.'/tests/FooTest.php', '<?php class FooTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><phpunit><testsuites/></phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->classBasedTestFiles)->toHaveCount(1);
});

it('merges directories from multiple testsuite elements', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Feature', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/UnitTest.php', '<?php class UnitTest {}');
    file_put_contents($this->tempDir.'/tests/Feature/FeatureTest.php', '<?php class FeatureTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="unit"><directory>tests/Unit</directory></testsuite>
    <testsuite name="feature"><directory>tests/Feature</directory></testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    sort($basenames);
    expect($basenames)->toBe(['FeatureTest.php', 'UnitTest.php']);
});

it('discovers function-based Pest test files via phpunit.xml config', function () {
    mkdir($this->tempDir.'/tests/Feature', 0777, true);
    file_put_contents(
        $this->tempDir.'/tests/Feature/SomethingTest.php',
        "<?php\nit('works', function() {});"
    );
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default"><directory>tests/Feature</directory></testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    expect($result->functionBasedTestFiles)->toHaveCount(1)
        ->and($result->classBasedTestFiles)->toBe([]);
});

it('honors custom suffix like TestCase.php', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/SomeTestCase.php', '<?php class SomeTestCase {}');
    file_put_contents($this->tempDir.'/tests/Unit/OldTest.php', '<?php class OldTest {}');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory suffix="TestCase.php">tests/Unit</directory>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    $basenames = array_map('basename', $result->classBasedTestFiles);
    expect($basenames)->toBe(['SomeTestCase.php']);
});

it('falls back to legacy when phpunit.xml exists but is a non-phpunit XML document', function () {
    mkdir($this->tempDir.'/tests', 0777, true);
    file_put_contents($this->tempDir.'/tests/FooTest.php', '<?php class FooTest {}');
    // Valid XML but not a phpunit config.
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><root><something>unrelated</something></root>'
    );

    $scanner = new TestDirectoryScanner();
    $result = $scanner->scan($this->tempDir);

    // Parser returns a result with no testsuites → hasTestSuites() false → legacy path runs.
    expect($result->classBasedTestFiles)->toHaveCount(1);
});
