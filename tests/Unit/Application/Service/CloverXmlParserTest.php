<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\CloverXmlParser;

it('parses a valid Clover XML file', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/project/src/Foo.php">
      <metrics statements="10" coveredstatements="8"/>
    </file>
    <file name="/project/src/Bar.php">
      <metrics statements="5" coveredstatements="5"/>
    </file>
  </project>
</coverage>
XML;

    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, $xml);

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/project');

    unlink($path);

    expect($result)->toHaveKey('src/Foo.php')
        ->and($result['src/Foo.php']['statements'])->toBe(10)
        ->and($result['src/Foo.php']['coveredStatements'])->toBe(8)
        ->and($result['src/Foo.php']['linerate'])->toBe(0.8)
        ->and($result)->toHaveKey('src/Bar.php')
        ->and($result['src/Bar.php']['linerate'])->toEqual(1.0);
});

it('returns empty array for non-existent file', function () {
    $parser = new CloverXmlParser();
    $result = $parser->parse('/non/existent/clover.xml', '/project');

    expect($result)->toBe([]);
});

it('returns empty array for invalid XML', function () {
    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, 'this is not xml <<<>>');

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/project');

    unlink($path);

    expect($result)->toBe([]);
});

it('handles files nested inside package elements', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <package name="App">
      <file name="/project/src/Service/MyService.php">
        <metrics statements="20" coveredstatements="15"/>
      </file>
    </package>
  </project>
</coverage>
XML;

    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, $xml);

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/project');

    unlink($path);

    expect($result)->toHaveKey('src/Service/MyService.php')
        ->and($result['src/Service/MyService.php']['statements'])->toBe(20)
        ->and($result['src/Service/MyService.php']['coveredStatements'])->toBe(15);
});

it('handles division by zero when statements is 0', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/project/src/Empty.php">
      <metrics statements="0" coveredstatements="0"/>
    </file>
  </project>
</coverage>
XML;

    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, $xml);

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/project');

    unlink($path);

    expect($result)->toHaveKey('src/Empty.php')
        ->and($result['src/Empty.php']['linerate'])->toBe(0.0);
});

it('normalizes file paths relative to project root', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/var/www/myapp/src/Controller.php">
      <metrics statements="4" coveredstatements="2"/>
    </file>
  </project>
</coverage>
XML;

    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, $xml);

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/var/www/myapp');

    unlink($path);

    expect($result)->toHaveKey('src/Controller.php')
        ->and($result)->not->toHaveKey('/var/www/myapp/src/Controller.php');
});

it('skips file elements without metrics', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/project/src/NoMetrics.php">
    </file>
    <file name="/project/src/WithMetrics.php">
      <metrics statements="3" coveredstatements="3"/>
    </file>
  </project>
</coverage>
XML;

    $path = tempnam(sys_get_temp_dir(), 'clover_') . '.xml';
    file_put_contents($path, $xml);

    $parser = new CloverXmlParser();
    $result = $parser->parse($path, '/project');

    unlink($path);

    expect($result)->not->toHaveKey('src/NoMetrics.php')
        ->and($result)->toHaveKey('src/WithMetrics.php');
});
