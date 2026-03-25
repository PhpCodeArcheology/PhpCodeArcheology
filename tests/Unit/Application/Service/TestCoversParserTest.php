<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\TestCoversParser;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory())->createForHostVersion();
    $this->tempDir = sys_get_temp_dir() . '/pca-covers-' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    array_map('unlink', glob($this->tempDir . '/*') ?: []);
    rmdir($this->tempDir);
});

function writeTempTestFile(string $dir, string $name, string $code): string
{
    $path = $dir . '/' . $name;
    file_put_contents($path, $code);
    return $path;
}

it('extracts @covers annotation with fully qualified name', function () {
    $file = writeTempTestFile($this->tempDir, 'FooTest.php', <<<PHP
<?php
/**
 * @covers \App\Service\FooService
 */
class FooTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->toHaveKey($file)
        ->and($result->coversMap[$file])->toContain('App\Service\FooService');
});

it('extracts @covers annotation with short name resolved via use-statement', function () {
    $file = writeTempTestFile($this->tempDir, 'BarTest.php', <<<PHP
<?php
use App\Service\BarService;

/**
 * @covers BarService
 */
class BarTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->toHaveKey($file)
        ->and($result->coversMap[$file])->toContain('App\Service\BarService');
});

it('extracts @coversClass annotation', function () {
    $file = writeTempTestFile($this->tempDir, 'BazTest.php', <<<PHP
<?php
/**
 * @coversClass \App\Util\Baz
 */
class BazTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->toHaveKey($file)
        ->and($result->coversMap[$file])->toContain('App\Util\Baz');
});

it('extracts #[CoversClass(Foo::class)] PHP 8 attribute', function () {
    $file = writeTempTestFile($this->tempDir, 'AttrTest.php', <<<PHP
<?php
use App\Domain\Widget;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Widget::class)]
class AttrTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->toHaveKey($file)
        ->and($result->coversMap[$file])->toContain('App\Domain\Widget');
});

it('strips ::methodName from @covers ClassName::method', function () {
    $file = writeTempTestFile($this->tempDir, 'MethodTest.php', <<<PHP
<?php
/**
 * @covers \App\Service\Qux::doSomething
 */
class MethodTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap[$file])->toContain('App\Service\Qux')
        ->and($result->coversMap[$file])->not->toContain('App\Service\Qux::doSomething');
});

it('filters out PHPUnit use-statements from production uses', function () {
    $file = writeTempTestFile($this->tempDir, 'FilterTest.php', <<<PHP
<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use App\Service\RealService;

class FilterTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->useStatementsMap)->toHaveKey($file)
        ->and($result->useStatementsMap[$file])->toContain('App\Service\RealService')
        ->and($result->useStatementsMap[$file])->not->toContain('PHPUnit\Framework\TestCase')
        ->and($result->useStatementsMap[$file])->not->toContain('PHPUnit\Framework\Attributes\CoversClass');
});

it('filters out Mockery use-statements from production uses', function () {
    $file = writeTempTestFile($this->tempDir, 'MockeryTest.php', <<<PHP
<?php
use Mockery\MockInterface;
use App\Repository\ItemRepo;

class MockeryTest {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->useStatementsMap)->toHaveKey($file)
        ->and($result->useStatementsMap[$file])->toContain('App\Repository\ItemRepo')
        ->and($result->useStatementsMap[$file])->not->toContain('Mockery\MockInterface');
});

it('keeps production class use-statements', function () {
    $file = writeTempTestFile($this->tempDir, 'ProdUseTest.php', <<<PHP
<?php
use PHPUnit\Framework\TestCase;
use App\Http\Controller;
use App\Http\Request;

class ProdUseTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->useStatementsMap[$file])->toContain('App\Http\Controller')
        ->and($result->useStatementsMap[$file])->toContain('App\Http\Request');
});

it('returns empty result for file with no @covers', function () {
    $file = writeTempTestFile($this->tempDir, 'NoCoversTest.php', <<<PHP
<?php
class NoCoversTest extends TestCase
{
    public function testSomething(): void {}
}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->not->toHaveKey($file);
});

it('handles parse errors gracefully and returns empty result', function () {
    $file = writeTempTestFile($this->tempDir, 'BrokenTest.php', <<<PHP
<?php
this is not valid php <<<>>;
class
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->not->toHaveKey($file);
});

it('handles multiple @covers annotations on one class', function () {
    $file = writeTempTestFile($this->tempDir, 'MultiCoversTest.php', <<<PHP
<?php
/**
 * @covers \App\Service\Alpha
 * @covers \App\Service\Beta
 * @covers \App\Service\Gamma
 */
class MultiCoversTest extends TestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->coversMap)->toHaveKey($file)
        ->and($result->coversMap[$file])->toContain('App\Service\Alpha')
        ->and($result->coversMap[$file])->toContain('App\Service\Beta')
        ->and($result->coversMap[$file])->toContain('App\Service\Gamma')
        ->and($result->coversMap[$file])->toHaveCount(3);
});

it('filters out classes with TestCase suffix from use-statements', function () {
    $file = writeTempTestFile($this->tempDir, 'SuffixTest.php', <<<PHP
<?php
use App\Testing\CustomTestCase;
use App\Service\MyService;

class SuffixTest extends CustomTestCase {}
PHP);

    $coversParser = new TestCoversParser($this->parser);
    $result = $coversParser->parse([$file]);

    expect($result->useStatementsMap[$file])->toContain('App\Service\MyService')
        ->and($result->useStatementsMap[$file])->not->toContain('App\Testing\CustomTestCase');
});
