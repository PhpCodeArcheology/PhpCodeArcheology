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

// ---------------------------------------------------------------------------
// <source> scope parsing (PHPUnit 10+ coverage scope definition)
// ---------------------------------------------------------------------------

it('reports hasSourceScope() false when <source> is missing', function () {
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><phpunit/>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->hasSourceScope())->toBeFalse()
        ->and($result->sourceIncludeDirectories)->toBe([])
        ->and($result->sourceExcludeDirectories)->toBe([]);
});

it('treats every path as in-scope when no <source> is configured', function () {
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?><phpunit/>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->isInSourceScope('/any/absolute/path.php'))->toBeTrue();
});

it('parses <source><include><directory> entries', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->hasSourceScope())->toBeTrue()
        ->and($result->sourceIncludeDirectories)->toHaveCount(1)
        ->and($result->sourceIncludeDirectories[0])->toBe(realpath($this->tempDir.'/src'));
});

it('parses <source><include><file> entries', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    file_put_contents($this->tempDir.'/src/Kernel.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <file>src/Kernel.php</file>
    </include>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->sourceIncludeFiles)->toHaveCount(1)
        ->and($result->sourceIncludeFiles[0])->toBe(realpath($this->tempDir.'/src/Kernel.php'));
});

it('parses <source><exclude><directory> entries', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    mkdir($this->tempDir.'/src/DataFixtures', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
    <exclude>
      <directory>src/DataFixtures</directory>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->sourceExcludeDirectories)->toHaveCount(1)
        ->and($result->sourceExcludeDirectories[0])->toBe(realpath($this->tempDir.'/src/DataFixtures'));
});

it('parses <source><exclude><file> entries', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    file_put_contents($this->tempDir.'/src/Kernel.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
    <exclude>
      <file>src/Kernel.php</file>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->sourceExcludeFiles)->toHaveCount(1)
        ->and($result->sourceExcludeFiles[0])->toBe(realpath($this->tempDir.'/src/Kernel.php'));
});

it('accepts <source> with only <exclude> (no <include>)', function () {
    mkdir($this->tempDir.'/src/DataFixtures', 0777, true);
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <exclude>
      <directory>src/DataFixtures</directory>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->hasSourceScope())->toBeTrue()
        ->and($result->sourceIncludeDirectories)->toBe([])
        ->and($result->sourceExcludeDirectories)->toHaveCount(1);
});

it('isInSourceScope() returns true for files under an include directory', function () {
    mkdir($this->tempDir.'/src/Module', 0777, true);
    file_put_contents($this->tempDir.'/src/Module/Foo.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $fooPath = realpath($this->tempDir.'/src/Module/Foo.php');
    expect($result->isInSourceScope($fooPath))->toBeTrue();
});

it('isInSourceScope() returns false for files under an exclude directory', function () {
    mkdir($this->tempDir.'/src/DataFixtures', 0777, true);
    file_put_contents($this->tempDir.'/src/DataFixtures/UserFixtures.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
    <exclude>
      <directory>src/DataFixtures</directory>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $fixturePath = realpath($this->tempDir.'/src/DataFixtures/UserFixtures.php');
    expect($result->isInSourceScope($fixturePath))->toBeFalse();
});

it('isInSourceScope() returns false for files outside any include directory', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    mkdir($this->tempDir.'/other', 0777, true);
    file_put_contents($this->tempDir.'/other/Something.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $outsidePath = realpath($this->tempDir.'/other/Something.php');
    expect($result->isInSourceScope($outsidePath))->toBeFalse();
});

it('isInSourceScope() lets <exclude> win over <include> for the same path', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    file_put_contents($this->tempDir.'/src/Kernel.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src</directory>
    </include>
    <exclude>
      <file>src/Kernel.php</file>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $kernelPath = realpath($this->tempDir.'/src/Kernel.php');
    expect($result->isInSourceScope($kernelPath))->toBeFalse();
});

it('isInSourceScope() matches <include><file> exactly', function () {
    mkdir($this->tempDir.'/src', 0777, true);
    file_put_contents($this->tempDir.'/src/Target.php', '<?php');
    file_put_contents($this->tempDir.'/src/Other.php', '<?php');
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <file>src/Target.php</file>
    </include>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    $targetPath = realpath($this->tempDir.'/src/Target.php');
    $otherPath = realpath($this->tempDir.'/src/Other.php');
    expect($result->isInSourceScope($targetPath))->toBeTrue()
        ->and($result->isInSourceScope($otherPath))->toBeFalse();
});

it('silently drops unresolvable <source> paths', function () {
    file_put_contents(
        $this->tempDir.'/phpunit.xml',
        '<?xml version="1.0"?>
<phpunit>
  <source>
    <include>
      <directory>src/does-not-exist</directory>
    </include>
    <exclude>
      <file>src/also-missing.php</file>
    </exclude>
  </source>
</phpunit>'
    );

    $parser = new PhpunitConfigParser();
    $result = $parser->parse($this->tempDir);

    expect($result->sourceIncludeDirectories)->toBe([])
        ->and($result->sourceExcludeFiles)->toBe([])
        ->and($result->hasSourceScope())->toBeFalse();
});
