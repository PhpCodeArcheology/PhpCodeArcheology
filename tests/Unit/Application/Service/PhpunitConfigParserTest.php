<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\PhpunitConfigParser;
use PhpCodeArch\Application\Service\PhpunitConfigResult;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pca-phpunit-cfg-'.uniqid();
    mkdir($this->tempDir, 0777, true);
    $this->tempDirReal = realpath($this->tempDir);
});

afterEach(function () {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempDir);
});

it('returns null when no phpunit config file exists', function () {
    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result)->toBeNull();
});

it('returns null on malformed XML', function () {
    file_put_contents($this->tempDir.'/phpunit.xml', '<not valid xml<');

    // Swallow STDERR warning noise so test output stays clean.
    $parser = new PhpunitConfigParser();
    ob_start();
    $result = @$parser->parse($this->tempDir);
    ob_end_clean();

    expect($result)->toBeNull();
});

it('returns empty testSuites when phpunit has no testsuites section', function () {
    file_put_contents($this->tempDir.'/phpunit.xml', '<?xml version="1.0"?><phpunit/>');

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result)->toBeInstanceOf(PhpunitConfigResult::class)
        ->and($result->found)->toBeTrue()
        ->and($result->testSuites)->toBe([])
        ->and($result->hasTestSuites())->toBeFalse();
});

it('returns empty testSuites when testsuites wrapper has no children', function () {
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><phpunit><testsuites/></phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->testSuites)->toBe([])
        ->and($result->hasTestSuites())->toBeFalse();
});

it('parses a single directory with default suffix', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->testSuites)->toHaveCount(1)
        ->and($result->testSuites[0]->name)->toBe('default')
        ->and($result->getAllDirectories())->toHaveCount(1)
        ->and($result->getAllDirectories()[0]->absolutePath)->toBe(realpath($this->tempDir.'/tests/Unit'))
        ->and($result->getAllDirectories()[0]->suffix)->toBe('Test.php')
        ->and($result->getAllDirectories()[0]->prefix)->toBe('')
        ->and($result->hasTestSuites())->toBeTrue();
});

it('respects custom suffix attribute', function () {
    mkdir($this->tempDir.'/tests/Integration', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="integration">
      <directory suffix="Integration.php">tests/Integration</directory>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->getAllDirectories()[0]->suffix)->toBe('Integration.php');
});

it('respects prefix attribute', function () {
    mkdir($this->tempDir.'/tests', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory prefix="IT_">tests</directory>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->getAllDirectories()[0]->prefix)->toBe('IT_');
});

it('parses multiple directories in one testsuite with different suffixes', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Acceptance', 0777, true);
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

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $dirs = $result->getAllDirectories();
    expect($dirs)->toHaveCount(2)
        ->and($dirs[0]->suffix)->toBe('Test.php')
        ->and($dirs[1]->suffix)->toBe('Cest.php');
});

it('merges directories across multiple testsuite elements', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Feature', 0777, true);
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

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->testSuites)->toHaveCount(2)
        ->and($result->getAllDirectories())->toHaveCount(2);
});

it('parses explicit <file> entries into getAllExplicitFiles', function () {
    mkdir($this->tempDir.'/tests/Special', 0777, true);
    file_put_contents($this->tempDir.'/tests/Special/OneOff.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="special"><file>tests/Special/OneOff.php</file></testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->getAllExplicitFiles())->toHaveCount(1)
        ->and($result->getAllExplicitFiles()[0])->toBe(realpath($this->tempDir.'/tests/Special/OneOff.php'));
});

it('parses <exclude> directory entries', function () {
    mkdir($this->tempDir.'/tests/Unit/Legacy', 0777, true);
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

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $excluded = realpath($this->tempDir.'/tests/Unit/Legacy');
    expect($result->testSuites[0]->excludedPaths)->toContain($excluded);
});

it('parses <exclude> file entries', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents($this->tempDir.'/tests/Unit/BrokenTest.php', '<?php');
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

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $excluded = realpath($this->tempDir.'/tests/Unit/BrokenTest.php');
    expect($result->testSuites[0]->excludedPaths)->toContain($excluded);
});

it('silently drops exclude paths that do not exist on disk', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
      <exclude>tests/Unit/DoesNotExist</exclude>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();

    // Must not throw and must still return a valid result.
    $result = $parser->parse($this->tempDir);

    expect($result)->not->toBeNull()
        ->and($result->testSuites[0]->excludedPaths)->toBe([]);
});

it('picks phpunit.xml over phpunit.xml.dist when both exist', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/Dist', 0777, true);

    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><phpunit><testsuites><testsuite name="user"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>'
    );
    file_put_contents(
        $this->tempDir.'/phpunit.xml.dist',
        '<?xml version="1.0"?><phpunit><testsuites><testsuite name="dist"><directory>tests/Dist</directory></testsuite></testsuites></phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->testSuites[0]->name)->toBe('user')
        ->and($result->getAllDirectories()[0]->absolutePath)->toBe(realpath($this->tempDir.'/tests/Unit'));
});

it('falls back to phpunit.dist.xml when it is the only variant', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.dist.xml',
        '<?xml version="1.0"?><phpunit><testsuites><testsuite name="dist-only"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result)->not->toBeNull()
        ->and($result->testSuites[0]->name)->toBe('dist-only');
});

it('isExcluded trailing-separator semantics: does not over-match sibling directories', function () {
    mkdir($this->tempDir.'/tests/Unit', 0777, true);
    mkdir($this->tempDir.'/tests/UnitLegacy', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests/Unit</directory>
      <exclude>tests/Unit</exclude>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $unitLegacyFile = realpath($this->tempDir.'/tests/UnitLegacy').'/SomeTest.php';
    $unitChildFile = realpath($this->tempDir.'/tests/Unit').'/SomeTest.php';

    expect($result->isExcluded($unitLegacyFile))->toBeFalse()
        ->and($result->isExcluded($unitChildFile))->toBeTrue();
});

it('isExcluded exact file match', function () {
    mkdir($this->tempDir.'/tests', 0777, true);
    file_put_contents($this->tempDir.'/tests/BrokenTest.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory>tests</directory>
      <exclude>tests/BrokenTest.php</exclude>
    </testsuite>
  </testsuites>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $brokenAbs = realpath($this->tempDir.'/tests/BrokenTest.php');
    $otherAbs = realpath($this->tempDir.'/tests').'/OtherTest.php';

    expect($result->isExcluded($brokenAbs))->toBeTrue()
        ->and($result->isExcluded($otherAbs))->toBeFalse();
});
