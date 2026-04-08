<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\ConfigFile\ConfigFileParserJson;
use PhpCodeArch\Application\ConfigFile\ConfigFileParserYaml;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileNotFoundException;
use Symfony\Component\Yaml\Yaml;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function writeTempJson(array $data): string
{
    $file = tempnam(sys_get_temp_dir(), 'phpca_json_').'.json';
    file_put_contents($file, json_encode($data));

    return $file;
}

function writeTempYaml(string $content): string
{
    $file = tempnam(sys_get_temp_dir(), 'phpca_yaml_').'.yaml';
    file_put_contents($file, $content);

    return $file;
}

function parseJsonFile(string $file, Config $config): void
{
    $content = file_get_contents($file);
    if (false === $content) {
        throw new RuntimeException('Failed to read temp file: '.$file);
    }
    (new ConfigFileParserJson($file))->parseJson($content, $config);
}

// ──────────────────────────────────────────────────────────────────────────────
// JSON parser — full config
// ──────────────────────────────────────────────────────────────────────────────

it('json: maps include to files', function () {
    $file = writeTempJson(['include' => ['src/', 'lib/']]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('files'))->toBe(['src/', 'lib/']);
});

it('json: maps exclude', function () {
    $file = writeTempJson(['exclude' => ['vendor/', 'tests/']]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('exclude'))->toBe(['vendor/', 'tests/']);
});

it('json: maps extensions', function () {
    $file = writeTempJson(['extensions' => ['php', 'phtml']]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('extensions'))->toBe(['php', 'phtml']);
});

it('json: maps memoryLimit string', function () {
    $file = writeTempJson(['memoryLimit' => '512M']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('memoryLimit'))->toBe('512M');
});

it('json: maps memoryLimit -1', function () {
    $file = writeTempJson(['memoryLimit' => '-1']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('memoryLimit'))->toBe('-1');
});

it('json: maps reportType', function () {
    $file = writeTempJson(['reportType' => 'json']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('reportType'))->toBe('json');
});

it('json: maps packageSize', function () {
    $file = writeTempJson(['packageSize' => 5]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('packageSize'))->toBe(5);
});

it('json: maps framework', function () {
    $file = writeTempJson(['framework' => 'symfony']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('framework'))->toBe('symfony');
});

it('json: maps php', function () {
    $file = writeTempJson(['php' => '8.2']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('php'))->toBe('8.2');
});

it('json: maps acknowledgedVersion', function () {
    $file = writeTempJson(['acknowledgedVersion' => '2.8.0']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('acknowledgedVersion'))->toBe('2.8.0');
});

it('json: maps history', function () {
    $file = writeTempJson(['history' => ['keep' => 10]]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    // history is not a mapped key — no assertion; just verifying no crash
    expect(true)->toBeTrue();
});

it('json: sets configFileDir from file path', function () {
    $file = writeTempJson([]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('configFileDir'))->toBe(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir());
});

// ──────────────────────────────────────────────────────────────────────────────
// JSON parser — partial config / missing keys
// ──────────────────────────────────────────────────────────────────────────────

it('json: partial config does not crash', function () {
    $file = writeTempJson(['include' => ['src/']]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('exclude'))->toBeNull();
    expect($config->get('extensions'))->toBeNull();
    expect($config->get('memoryLimit'))->toBeNull();
    expect($config->get('reportType'))->toBeNull();
});

it('json: empty JSON object does not crash', function () {
    $file = writeTempJson([]);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson('{}', $config);
    unlink($file);

    expect($config->get('files'))->toBeNull();
});

it('json: invalid JSON does not crash', function () {
    $file = tempnam(sys_get_temp_dir(), 'phpca_json_').'.json';
    file_put_contents($file, 'not valid json }{');
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson('not valid json }{', $config);
    unlink($file);

    expect($config->get('files'))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// JSON parser — CLI precedence (reportType)
// ──────────────────────────────────────────────────────────────────────────────

it('json: CLI reportType is not overwritten by config file', function () {
    $file = writeTempJson(['reportType' => 'markdown']);
    $config = new Config();
    $config->set('reportType', 'html'); // simulates CLI flag already set
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('reportType'))->toBe('html');
});

it('json: config file reportType is used when CLI did not set it', function () {
    $file = writeTempJson(['reportType' => 'markdown']);
    $config = new Config();
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    expect($config->get('reportType'))->toBe('markdown');
});

// ──────────────────────────────────────────────────────────────────────────────
// JSON parser — reportDir resolved relative to runningDir
// ──────────────────────────────────────────────────────────────────────────────

it('json: relative reportDir is resolved against runningDir', function () {
    $runningDir = sys_get_temp_dir();
    $relativeDir = 'phpca_test_reportdir_'.uniqid();
    $expectedPath = $runningDir.DIRECTORY_SEPARATOR.$relativeDir;

    $file = writeTempJson(['reportDir' => $relativeDir]);
    $config = new Config();
    $config->set('runningDir', $runningDir);
    (new ConfigFileParserJson($file))->parseJson(file_get_contents($file), $config);
    unlink($file);

    $reportDir = $config->get('reportDir');
    expect($reportDir)->toBe(realpath($expectedPath) ?: $expectedPath);

    // cleanup
    if (is_dir($expectedPath)) {
        rmdir($expectedPath);
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// JSON parser — coverageFile
// ──────────────────────────────────────────────────────────────────────────────

it('json: maps absolute coverageFile path', function () {
    // Create a real temp file so realpath() resolves
    $coverageFile = tempnam(sys_get_temp_dir(), 'phpca_clover_').'.xml';
    file_put_contents($coverageFile, '<?xml version="1.0"?><coverage/>');

    $file = writeTempJson(['coverageFile' => $coverageFile]);
    $config = new Config();
    parseJsonFile($file, $config);
    unlink($file);
    unlink($coverageFile);

    expect($config->get('coverageFile'))->toBe(realpath($coverageFile) ?: $coverageFile);
});

it('json: keeps unresolved absolute coverageFile path as raw value', function () {
    $rawPath = '/nonexistent/path/clover.xml';
    $file = writeTempJson(['coverageFile' => $rawPath]);
    $config = new Config();
    parseJsonFile($file, $config);
    unlink($file);

    expect($config->get('coverageFile'))->toBe($rawPath);
});

it('json: relative coverageFile is resolved against runningDir', function () {
    $runningDir = sys_get_temp_dir();
    $relativeName = 'phpca_test_clover_'.uniqid().'.xml';
    $absolutePath = $runningDir.DIRECTORY_SEPARATOR.$relativeName;
    file_put_contents($absolutePath, '<?xml version="1.0"?><coverage/>');

    $file = writeTempJson(['coverageFile' => $relativeName]);
    $config = new Config();
    $config->set('runningDir', $runningDir);
    parseJsonFile($file, $config);
    unlink($file);

    expect($config->get('coverageFile'))->toBe(realpath($absolutePath) ?: $absolutePath);

    unlink($absolutePath);
});

it('json: CLI coverageFile is not overwritten by config file', function () {
    $file = writeTempJson(['coverageFile' => 'config-clover.xml']);
    $config = new Config();
    $config->set('coverageFile', 'cli-clover.xml'); // simulates CLI flag already set
    parseJsonFile($file, $config);
    unlink($file);

    expect($config->get('coverageFile'))->toBe('cli-clover.xml');
});

it('json: non-scalar coverageFile value is ignored', function () {
    $file = writeTempJson(['coverageFile' => ['key' => 'value']]);
    $config = new Config();
    parseJsonFile($file, $config);
    unlink($file);

    expect($config->get('coverageFile'))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// YAML parser — full config
// ──────────────────────────────────────────────────────────────────────────────

it('yaml: maps include to files', function () {
    $file = writeTempYaml("include:\n  - src/\n  - lib/\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('files'))->toBe(['src/', 'lib/']);
});

it('yaml: maps exclude', function () {
    $file = writeTempYaml("exclude:\n  - vendor/\n  - tests/\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('exclude'))->toBe(['vendor/', 'tests/']);
});

it('yaml: maps extensions', function () {
    $file = writeTempYaml("extensions:\n  - php\n  - phtml\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('extensions'))->toBe(['php', 'phtml']);
});

it('yaml: maps memoryLimit string', function () {
    $file = writeTempYaml("memoryLimit: '512M'\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('memoryLimit'))->toBe('512M');
});

it('yaml: maps memoryLimit -1', function () {
    $file = writeTempYaml("memoryLimit: '-1'\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('memoryLimit'))->toBe('-1');
});

it('yaml: maps reportType', function () {
    $file = writeTempYaml("reportType: json\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('reportType'))->toBe('json');
});

it('yaml: maps packageSize', function () {
    $file = writeTempYaml("packageSize: 5\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('packageSize'))->toBe(5);
});

it('yaml: maps framework', function () {
    $file = writeTempYaml("framework: symfony\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('framework'))->toBe('symfony');
});

it('yaml: maps php version', function () {
    $file = writeTempYaml("php: '8.2'\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('php'))->toBe('8.2');
});

it('yaml: maps acknowledgedVersion', function () {
    $file = writeTempYaml("acknowledgedVersion: '2.8.0'\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('acknowledgedVersion'))->toBe('2.8.0');
});

// ──────────────────────────────────────────────────────────────────────────────
// YAML parser — partial config / missing keys
// ──────────────────────────────────────────────────────────────────────────────

it('yaml: partial config does not crash', function () {
    $file = writeTempYaml("include:\n  - src/\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('exclude'))->toBeNull();
    expect($config->get('memoryLimit'))->toBeNull();
    expect($config->get('reportType'))->toBeNull();
});

it('yaml: empty file does not crash', function () {
    $file = writeTempYaml('');
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('files'))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// YAML parser — CLI precedence
// ──────────────────────────────────────────────────────────────────────────────

it('yaml: CLI reportType is not overwritten by config file', function () {
    $file = writeTempYaml("reportType: markdown\n");
    $config = new Config();
    $config->set('reportType', 'html');
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    expect($config->get('reportType'))->toBe('html');
});

// ──────────────────────────────────────────────────────────────────────────────
// YAML parser — coverageFile
// ──────────────────────────────────────────────────────────────────────────────

it('yaml: maps coverageFile (raw path when unresolved)', function () {
    $file = writeTempYaml("coverageFile: var/reports/clover.xml\n");
    $config = new Config();
    (new ConfigFileParserYaml($file, new Yaml()))->parse($config);
    unlink($file);

    // Without runningDir set and without an existing file, the raw path is kept
    expect($config->get('coverageFile'))->toBe('var/reports/clover.xml');
});

// ──────────────────────────────────────────────────────────────────────────────
// YAML parser — error cases
// ──────────────────────────────────────────────────────────────────────────────

it('yaml: throws ConfigFileNotFoundException when file does not exist', function () {
    $config = new Config();
    (new ConfigFileParserYaml('/nonexistent/path/config.yaml', new Yaml()))->parse($config);
})->throws(ConfigFileNotFoundException::class);
